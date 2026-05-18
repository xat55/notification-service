<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\GatewayService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(public $notificationId, public $queue = "default")
    {
    }

    public function handle(GatewayService $gateway): void
    {
        $notification = Notification::find($this->notificationId);

        if (!$notification || $notification->status !== "queued") {
            return;
        }

        $notification->update([
            "status" => "sent",
            "sent_at" => now(),
        ]);

        $result = $gateway->send(
            $notification->channel,
            $notification->subscriber_id,
            $notification->message
        );

        if ($result["success"]) {
            $notification->update([
                "status" => "delivered",
                "delivered_at" => now(),
                "provider_message_id" => $result["message_id"] ?? null,
            ]);
        } else {
            if ($notification->retry_count < 3) {
                $notification->increment("retry_count");
                $this->release(60 * $notification->retry_count);
            } else {
                $notification->update([
                    "status" => "failed",
                    "error_message" => $result["error"] ?? "Unknown error",
                ]);
            }
        }
    }
}
