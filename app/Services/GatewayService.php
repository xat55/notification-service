<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GatewayService
{
    private const CACHE_TTL = 86400; // 24 часа
    private const FAILURE_RATE = 10; // 10% вероятность ошибки

    public function send(string $channel, string $recipientId, string $message, string $idempotencyKey): array
    {
        $flagKey = "gateway:{$idempotencyKey}:flag";
        $idKey = "gateway:{$idempotencyKey}:message_id";

        // 1. Проверка дубля
        if (Redis::get($flagKey)) {
            Log::info("Gateway: duplicate request detected", [
                'idempotency_key' => $idempotencyKey,
                'message_id' => Redis::get($idKey),
            ]);

            return [
                'success' => true,
                'message_id' => Redis::get($idKey),
                'cached' => true,
                'duplicate' => true
            ];
        }

        // 2. Симуляция задержки
        usleep(rand(10000, 50000));

        // 3. Симуляция ошибок
        if (rand(1, 100) <= self::FAILURE_RATE) {
            $errorType = $this->getRandomError();

            Log::warning("Gateway: provider error", [
                'idempotency_key' => $idempotencyKey,
                'error_type' => $errorType
            ]);

            return [
                'success' => false,
                'error' => $errorType,
                'retryable' => $errorType !== 'invalid_recipient'
            ];
        }

        // 4. Успешная отправка
        $messageId = $this->generateMessageId($channel);

        // 5. Сохраняем только необходимое
        Redis::set($flagKey, '1', 'EX', self::CACHE_TTL);
        Redis::set($idKey, $messageId, 'EX', self::CACHE_TTL);

        Log::info("Gateway: notification sent", [
            'idempotency_key' => $idempotencyKey,
            'message_id' => $messageId,
            'channel' => $channel
        ]);

        return [
            'success' => true,
            'message_id' => $messageId,
            'provider' => $channel === 'sms' ? 'SMSGateway' : 'EmailGateway'
        ];
    }

    private function generateMessageId(string $channel): string
    {
        $prefix = $channel === 'sms' ? 'SM' : 'EM';
        return "{$prefix}" . now()->timestamp . "_" . strtoupper(Str::random(8));
    }

    private function getRandomError(): string
    {
        $errors = [
            'provider_timeout' => 30,
            'rate_limit_exceeded' => 25,
            'invalid_recipient' => 15,
            'network_error' => 15,
            'service_unavailable' => 10,
            'authentication_failed' => 5
        ];

        $weights = array_values($errors);
        $total = array_sum($weights);
        $rand = rand(1, $total);

        $cumulative = 0;
        foreach ($errors as $error => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $error;
            }
        }

        return array_keys($errors)[0];
    }
}
