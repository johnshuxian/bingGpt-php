<?php

namespace App\Jobs;

use App\Http\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class ChatGpt implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private array $params;

    public int $tries = 0;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(array $params)
    {
        $this->params = $params;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $message_id = $this->params['message']['message_id'] ?? '';
        $id         = $this->params['message']['chat']['id'] ?? 0;

        $key = $message_id . '-' . $id;

        if ($message_id && Redis::connection()->client()->set('lock:' . $key, 1, ['nx', 'ex'=>300])) {
            TelegramService::getInstance()->telegram($this->params, 'chatGpt');
        }
    }
}
