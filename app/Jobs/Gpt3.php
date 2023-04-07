<?php

namespace App\Jobs;

use App\Http\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class Gpt3 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
            TelegramService::getInstance()->telegram($this->params, 'gpt3',env('TELEGRAM_BOT_TOKEN_2'), env('TELEGRAM_BOT_NAME_2'),$key);
        }

        Redis::client()->del('last_message_id:' . $key);
    }
}
