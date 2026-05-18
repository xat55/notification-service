<?php

namespace App\Services;

use App\Models\Notification;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

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

        foreach ($recipientIds as $recipientId) {
            $key = $idempotencyKey ?? $batchId . ":" . $recipientId;

            if (Redis::exists("idempotent:{$key}")) {
                continue;
            }

            DB::transaction(function () use ($channel, $message, $recipientId, $priority, $key, $batchId) {
                $notification = Notification::create([
                    "subscriber_id" => $recipientId,
                    "channel" => $channel,
                    "message" => $message,
                    "priority" => $priority,
                    "status" => "queued",
                    "idempotency_key" => $key,
                    "queued_at" => now(),
                ]);

                Redis::sadd("batch:{$batchId}:ids", $notification->id);
                Redis::expire("batch:{$batchId}:ids", 3600);

                $queueName = $priority === "high" ? "high_priority" : "marketing";
                SendNotificationJob::dispatch($notification->id, $queueName);

                Redis::setex("idempotent:{$key}", self::IDEMPOTENCY_TTL, $notification->id);
            });
        }

        return ["batch_id" => $batchId];
    }

    public function getHistory(string $subscriberId): array
    {
        return Notification::where("subscriber_id", $subscriberId)
            ->orderBy("created_at", "desc")
            ->get()
            ->toArray();
    }
}
