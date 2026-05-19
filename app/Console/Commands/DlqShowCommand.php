<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class DlqShowCommand extends Command
{
    protected $signature = 'dlq:show {amount=10}';
    protected $description = 'Показать записи из dead letter queue';

    private const DLQ_REDIS_KEY = "dlq:notifications";

    public function handle(): int
    {
        $amount = (int) $this->argument("amount");
        $size = Redis::llen(self::DLQ_REDIS_KEY);

        if ($size === 0) {
            $this->info("DLQ пуст.");
            return self::SUCCESS;
        }

        $this->info("DLQ размер: {$size} записей");
        $this->newLine();

        $entries = Redis::lrange(self::DLQ_REDIS_KEY, 0, $amount - 1);

        $this->table(
            ["Notification ID", "Subscriber", "Channel", "Error", "Retries", "Failed At"],
            array_map(function ($entry) {
                $data = json_decode($entry, true);
                return [
                    $data["notification_id"] ?? "-",
                    $data["subscriber_id"] ?? "-",
                    $data["channel"] ?? "-",
                    $data["error"] ?? "-",
                    $data["retry_count"] ?? "-",
                    $data["failed_at"] ?? "-",
                ];
            }, $entries)
        );

        return self::SUCCESS;
    }
}
