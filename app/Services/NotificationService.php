<?php

namespace App\Services;

use App\Models\Notification;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        $records = [];
        $skippedCount = 0;

        foreach ($recipientIds as $recipientId) {
            $key = $idempotencyKey ?? $batchId . ":" . $recipientId;
            $redisKey = "idempotent:{$key}";

            // Быстрый путь: ключ уже обработан ранее → пропускаем без обращения к БД.
            if (Redis::get($redisKey)) {
                $skippedCount++;
                continue;
            }

            $records[] = [
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

        if (empty($records)) {
            return ["batch_id" => $batchId, "skipped" => $skippedCount];
        }

        // БД — единственный источник правды для идемпотентности:
        // дубли отсекает unique-индекс (ON CONFLICT DO NOTHING) атомарно,
        // Redis-маркер ниже — лишь кэш, который ставится ТОЛЬКО после успешной
        // вставки. Поэтому «зависших» ключей без записей в БД не бывает.
        $inserted = []; // idempotency_key => id

        DB::transaction(function () use (&$inserted, &$skippedCount, $records, $batchId, $queueName) {
            foreach (array_chunk($records, 100) as $chunk) {
                DB::table("notifications")->insertOrIgnore($chunk);

                // Только строки, вставленные этим запросом (id генерируем сами,
                // чужие UUID сюда не попадут).
                $ours = Notification::whereIn("id", array_column($chunk, "id"))
                    ->pluck("id", "idempotency_key");

                foreach ($ours as $key => $notificationId) {
                    $inserted[$key] = $notificationId;
                }

                // Строки, не вставленные из-за конфликта по ключу, считаем пропущенными.
                $skippedCount += count($chunk) - count($ours);
            }

            // Redis-маркеры, батч-сет и джобы — только ПОСЛЕ коммита:
            // при откате транзакции этот колбэк не выполнится вовсе.
            DB::afterCommit(function () use ($inserted, $batchId, $queueName) {
                try {
                    foreach ($inserted as $key => $notificationId) {
                        Redis::set("idempotent:{$key}", "1", "EX", self::IDEMPOTENCY_TTL);
                        Redis::sadd("batch:{$batchId}:ids", $notificationId);
                        SendNotificationJob::dispatch($notificationId, $queueName);
                    }

                    Redis::expire("batch:{$batchId}:ids", 3600);
                } catch (Throwable $e) {
                    // Записи уже закоммичены в БД; Redis/очередь — вспомогательные.
                    // Не превращаем успешный коммит в 500: логируем для ручного разбора.
                    Log::error("Post-commit notification bookkeeping failed", [
                        "batch_id" => $batchId,
                        "error" => $e->getMessage(),
                    ]);
                }
            });
        });

        return [
            "batch_id" => $batchId,
            "created" => count($inserted),
            "skipped" => $skippedCount,
        ];
    }

    public function getHistory(string $subscriberId): array
    {
        return Notification::where("subscriber_id", $subscriberId)
            ->orderBy("created_at", "desc")
            ->get()
            ->toArray();
    }
}
