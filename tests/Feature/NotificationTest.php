<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Notification;
use Illuminate\Support\Facades\Redis;

class NotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
        Notification::truncate();
    }

    public function test_can_send_notifications()
    {
        $response = $this->postJson("/api/notifications/send", [
            "channel" => "sms",
            "message" => "Test message",
            "recipient_ids" => ["user1", "user2"],
            "priority" => "high"
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure(["batch_id", "message"]);
        
        $this->assertEquals(2, Notification::count());
    }

    public function test_can_get_notification_history()
    {
        Notification::create([
            "id" => "550e8400-e29b-41d4-a716-446655440000",
            "subscriber_id" => "user_test",
            "channel" => "email",
            "message" => "History test",
            "priority" => "low",
            "status" => "delivered",
            "idempotency_key" => "test_key_123",
            "queued_at" => now(),
            "delivered_at" => now(),
        ]);

        $response = $this->getJson("/api/subscribers/user_test/notifications");

        $response->assertStatus(200)
            ->assertJsonStructure([
                "subscriber_id",
                "notifications" => [
                    "*" => ["id", "status", "channel", "message"]
                ]
            ]);
        
        $this->assertCount(1, $response->json("notifications"));
    }

    public function test_idempotency_prevents_duplicates()
    {
        $payload = [
            "channel" => "sms",
            "message" => "Duplicate test",
            "recipient_ids" => ["user1"],
            "idempotency_key" => "unique-key-123"
        ];

        $this->postJson("/api/notifications/send", $payload);
        $this->postJson("/api/notifications/send", $payload);

        $this->assertEquals(1, Notification::count());
    }
}
