<?php

namespace App\Services;

use App\Models\Notification;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class NotificationService
{
    private const IDEMPOTENCY_TTL = 86400;

    public function sendBulk(
        string $channel,
        string $message,
        array $recipientIds,
        string $priority = "low",
        ?string $idempotencyKey = null
    ): array {
        $batchId = (string) Str::uuid();
        $queueName = $priority === "high" ? "high_priority" : "marketing";
        $now = now();

        $newRecords = [];
        $skippedCount = 0;

        foreach ($recipientIds as $recipientId) {
            $key = $idempotencyKey ?? $batchId . ":" . $recipientId;
            $redisKey = "idempotent:{$key}";

            $acquired = Redis::set($redisKey, "1", "EX", self::IDEMPOTENCY_TTL, "NX");

            if (!$acquired) {
                $skippedCount++;
                continue;
            }

            $newRecords[] = [
                "id" => (string) Str::uuid(),
                "subscriber_id" => $recipientId,
                "channel" => $channel,
                "message" => $message,
                "priority" => $priority,
                "status" => "queued",
                "idempotency_key" => $key,
                "queued_at" => $now,
                "created_at" => $now,
                "updated_at" => $now,
            ];
        }

        if (empty($newRecords)) {
            return ["batch_id" => $batchId, "skipped" => $skippedCount];
        }

        $insertedIds = [];

        try {
            DB::transaction(function () use (&$insertedIds, $newRecords, $batchId, $queueName) {
                $chunks = array_chunk($newRecords, 100);

                foreach ($chunks as $chunk) {
                    DB::table("notifications")->insert($chunk);

                    $keys = array_column($chunk, "idempotency_key");
                    $dbRecords = Notification::whereIn("idempotency_key", $keys)
                        ->pluck("id", "idempotency_key");

                    foreach ($keys as $key) {
                        if ($dbRecords->has($key)) {
                            $notificationId = $dbRecords->get($key);
                            $insertedIds[] = $notificationId;
                            Redis::sadd("batch:{$batchId}:ids", $notificationId);
                            SendNotificationJob::dispatch($notificationId, $queueName);
                        }
                    }
                }

                Redis::expire("batch:{$batchId}:ids", 3600);
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                $existingKeys = Notification::whereIn(
                    "idempotency_key",
                    array_column($newRecords, "idempotency_key")
                )->pluck("idempotency_key")->toArray();

                $retryRecords = array_filter($newRecords, function ($record) use ($existingKeys) {
                    return !in_array($record["idempotency_key"], $existingKeys);
                });

                if (!empty($retryRecords)) {
                    DB::transaction(function () use (&$insertedIds, $retryRecords, $batchId, $queueName) {
                        DB::table("notifications")->insert($retryRecords);

                        $keys = array_column($retryRecords, "idempotency_key");
                        $dbRecords = Notification::whereIn("idempotency_key", $keys)
                            ->pluck("id", "idempotency_key");

                        foreach ($keys as $key) {
                            if ($dbRecords->has($key)) {
                                $notificationId = $dbRecords->get($key);
                                $insertedIds[] = $notificationId;
                                Redis::sadd("batch:{$batchId}:ids", $notificationId);
                                SendNotificationJob::dispatch($notificationId, $queueName);
                            }
                        }

                        Redis::expire("batch:{$batchId}:ids", 3600);
                    });
                }
            } else {
                throw $e;
            }
        }

        return [
            "batch_id" => $batchId,
            "created" => count($insertedIds),
            "skipped" => $skippedCount,
        ];
    }

    /**
     * Проверка на unique constraint violation для PostgreSQL.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === "23505"
            || str_contains($e->getMessage(), "unique")
            || str_contains(strtolower($e->getMessage()), "duplicate");
    }

    public function getHistory(string $subscriberId): array
    {
        return Notification::where("subscriber_id", $subscriberId)
            ->orderBy("created_at", "desc")
            ->get()
            ->toArray();
    }
}
