<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        "subscriber_id",
        "channel",
        "message",
        "priority",
        "status",
        "idempotency_key",
        "provider_message_id",
        "error_message",
        "retry_count",
        "queued_at",
        "sent_at",
        "delivered_at",
    ];

    protected $casts = [
        "queued_at" => "datetime",
        "sent_at" => "datetime",
        "delivered_at" => "datetime",
    ];
}
