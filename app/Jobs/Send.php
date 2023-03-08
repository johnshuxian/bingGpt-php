<?php

namespace App\Jobs;

use App\Http\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class Send implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private mixed $bot_name;
    private mixed $bot_token;
    private mixed $chat_id;
    private mixed $text;

    /**
     * Create a new job instance.
     *
     * @param mixed $bot_name
     * @param mixed $bot_token
     * @param mixed $chat_id
     * @param mixed $text
     */
    public function __construct($bot_name, $bot_token, $chat_id, $text)
    {
        $this->bot_name  = $bot_name;
        $this->bot_token = $bot_token;
        $this->chat_id   = $chat_id;
        $this->text      = $text;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        TelegramService::bot($this->bot_token, $this->bot_name);

        TelegramService::sendTelegram($this->text, $this->chat_id);
    }
}
