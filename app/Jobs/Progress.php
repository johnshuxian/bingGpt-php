<?php

namespace App\Jobs;

use App\Http\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class Progress implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private mixed $bot_name;
    private mixed $bot_token;
    private mixed $chat_id;
    private mixed $text;

    private mixed $key;

    /**
     * Create a new job instance.
     *
     * @param mixed $bot_name
     * @param mixed $bot_token
     * @param mixed $chat_id
     * @param mixed $text
     */
    public function __construct($bot_name, $bot_token, $chat_id, $text,$key)
    {
        $this->bot_name  = $bot_name;
        $this->bot_token = $bot_token;
        $this->chat_id   = $chat_id;
        $this->text      = $text;
        $this->key       = $key;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        TelegramService::bot($this->bot_token, $this->bot_name);

        TelegramService::sendTelegram($this->text, $this->chat_id);

        $last_message_id = Redis::client()->get('last_message_id:' . $this->key);

        if (!$last_message_id) {
            $data = TelegramService::sendTelegram($this->text, $this->chat_id);

            $last_message_id = $data['data']['result']['message_id'] ?? 0;

            Redis::connection()->client()->set('last_message_id:' . $this->key, $last_message_id, ['nx', 'ex'=>3600]);
        } else {
            $data = TelegramService::updateTelegram($this->text, $this->chat_id,$last_message_id);
        }

        return $data;
    }
}
