<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TestQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function handle()
    {
        Log::info("Получено сообщение из очереди: " . json_encode($this->message));
        echo "Обработано: " . json_encode($this->message) . PHP_EOL;
    }
}
