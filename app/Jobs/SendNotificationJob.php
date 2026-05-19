<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\GatewayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    private const DLQ_REDIS_KEY = "dlq:notifications";
    private const DLQ_MAX_SIZE = 10000;

    public function __construct(public $notificationId, public $queue = "default")
    {
    }

    public function handle(GatewayService $gateway): void
    {
        $notification = Notification::find($this->notificationId);

        if (!$notification) {
            Log::warning("Notification not found", [
                "notification_id" => $this->notificationId,
            ]);
            return;
        }

        if ($notification->status !== "queued") {
            Log::info("Notification already processed", [
                "notification_id" => $this->notificationId,
                "status" => $notification->status,
            ]);
            return;
        }

        $result = $gateway->send(
            $notification->channel,
            $notification->subscriber_id,
            $notification->message
        );

        if ($result["success"]) {
            $notification->update([
                "status" => "delivered",
                "sent_at" => $notification->sent_at ?? now(),
                "delivered_at" => now(),
                "provider_message_id" => $result["message_id"] ?? null,
            ]);

            Log::info("Notification delivered", [
                "notification_id" => $notification->id,
                "provider_message_id" => $result["message_id"] ?? null,
            ]);
        } else {
            if (!$notification->sent_at) {
                $notification->update(["sent_at" => now()]);
            }

            $currentRetry = $notification->retry_count ?? 0;

            if ($currentRetry < $this->tries - 1) {
                $newRetryCount = $currentRetry + 1;
                $notification->update(["retry_count" => $newRetryCount]);

                $delay = 60 * $newRetryCount;
                Log::warning("Notification retry scheduled", [
                    "notification_id" => $notification->id,
                    "retry" => $newRetryCount,
                    "delay_seconds" => $delay,
                    "error" => $result["error"] ?? "Unknown",
                ]);

                $this->release($delay);
            } else {
                $notification->update([
                    "status" => "failed",
                    "error_message" => $result["error"] ?? "Max retries exceeded",
                ]);

                $this->pushToDeadLetterQueue($notification, $result);

                Log::error("Notification moved to DLQ after max retries", [
                    "notification_id" => $notification->id,
                    "subscriber_id" => $notification->subscriber_id,
                    "channel" => $notification->channel,
                    "error" => $result["error"] ?? "Unknown",
                ]);
            }
        }
    }

    /**
     * Обработка при полной неуспешной обработке джоба (Laravel framework level).
     * Срабатывает когда все tries исчерпаны и фреймворк помечает джоб как failed.
     */
    public function failed(Throwable $exception): void
    {
        $notification = Notification::find($this->notificationId);

        if ($notification) {
            $notification->update([
                "status" => "failed",
                "error_message" => $exception->getMessage(),
            ]);

            $this->pushToDeadLetterQueue($notification, [
                "error" => $exception->getMessage(),
                "trace" => $exception->getTraceAsString(),
            ]);
        }
    }

    /**
     * Помещение уведомления в dead letter queue (Redis list).
     * DLQ хранит записи для ручного разбора или автоматического reprocessing.
     */
    private function pushToDeadLetterQueue(Notification $notification, array $result): void
    {
        $dlqEntry = json_encode([
            "notification_id" => $notification->id,
            "subscriber_id" => $notification->subscriber_id,
            "channel" => $notification->channel,
            "message" => $notification->message,
            "priority" => $notification->priority,
            "error" => $result["error"] ?? "Unknown error",
            "retry_count" => $notification->retry_count,
            "failed_at" => now()->toIso8601String(),
            "provider_trace" => $result["trace"] ?? null,
        ]);

        Redis::pipeline(function ($pipe) use ($dlqEntry) {
            $pipe->lpush(self::DLQ_REDIS_KEY, $dlqEntry);
            $pipe->ltrim(self::DLQ_REDIS_KEY, 0, self::DLQ_MAX_SIZE - 1);
        });

        Log::info("Notification pushed to DLQ", [
            "notification_id" => $notification->id,
            "dlq_size" => Redis::llen(self::DLQ_REDIS_KEY),
        ]);
    }
}
