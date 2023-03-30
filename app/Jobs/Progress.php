<?php

namespace App\Jobs;

use App\Http\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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
    private mixed $response;

    private mixed $key;
    private $microtime;

    /**
     * Create a new job instance.
     *
     * @param mixed $bot_name
     * @param mixed $bot_token
     * @param mixed $chat_id
     * @param mixed $text
     * @param mixed $response
     * @param mixed $key
     */
    public function __construct($bot_name, $bot_token, $chat_id, $response, $key)
    {
        $this->bot_name      = $bot_name;
        $this->bot_token     = $bot_token;
        $this->chat_id       = $chat_id;
        $this->response      = $response;
        $this->key           = $key;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        TelegramService::bot($this->bot_token, $this->bot_name);

        if ($this->response['answer'] == $this->response['adaptive_cards']) {
            $this->response['adaptive_cards'] = '';
        }

        $adaptive_cards = trim(preg_replace('/\[\^(\d+)\^\]/', '', $this->response['adaptive_cards']));

        $answer = preg_replace('/\[\^(\d+)\^\]/', '', $this->response['answer']);

        $adaptive_cards = trim(str_replace($answer, '', $adaptive_cards));

        if (!trim($adaptive_cards ?: $answer)) {
            return true;
        }

        $text = '';

        if ($this->response['numUserMessagesInConversation']) {
            $text .= '(' . $this->response['numUserMessagesInConversation'] . '/' . $this->response['maxNumUserMessagesInConversation'] . ')';
        }

        if ($text) {
            $text .= PHP_EOL;
        }

        if ($answer) {
            $text .= $answer.PHP_EOL;
        }

        if ($text) {
            $text .= PHP_EOL;
        }

        $text .= $adaptive_cards;

        $last_message_id = Redis::client()->get('last_message_id:' . $this->key);

        if(Redis::connection()->client()->incr('nums:' . $this->key)>=5){
            return true;
        }

        Redis::connection()->client()->expire('nums:' . $this->key, 3600);

        if (!$last_message_id) {
            $data = TelegramService::sendTelegram($text, $this->chat_id);

            $last_message_id = $data['data']['result']['message_id'] ?? 0;

            Redis::connection()->client()->set('last_message_id:' . $this->key, $last_message_id, ['nx', 'ex' => 3600]);
        } else {
            $data = TelegramService::updateTelegram($text, $this->chat_id, $last_message_id);
        }

        Log::info('error:'.$last_message_id,$data);

        return $data;
    }
}
