<?php

namespace App\Services;

use Illuminate\Support\Str;

class GatewayService
{
    public function send(string $channel, string $recipientId, string $message): array
    {
        usleep(rand(10000, 50000));

        if (rand(1, 100) <= 10) {
            return [
                'success' => false,
                'error' => 'Provider temporarily unavailable'
            ];
        }

        return [
            'success' => true,
            'message_id' => (string) Str::uuid(),
            'provider' => $channel === 'sms' ? 'SMSGateway' : 'EmailGateway'
        ];
    }
}
