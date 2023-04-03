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

    /**
     * @var false|mixed
     */
    private mixed $return_redis;

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
    public function __construct($bot_name, $bot_token, $chat_id, $response, $key,$return_redis = false)
    {
        $this->bot_name      = $bot_name;
        $this->bot_token     = $bot_token;
        $this->chat_id       = $chat_id;
        $this->response      = $response;
        $this->key           = $key;
        $this->return_redis  = $return_redis;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        TelegramService::bot($this->bot_token, $this->bot_name);

        if($this->return_redis){
            $text = Redis::connection()->client()->get('last_message_answer:'.$this->key);

            Log::info('发送最新的一条消息:'.$text);
            goto go;
        }

        if ($this->response['answer'] == $this->response['adaptive_cards']) {
            $this->response['adaptive_cards'] = '';
        }

        $adaptive_cards = trim(preg_replace('/\[\^(\d+)\^\]/', '', $this->response['adaptive_cards']));

        $answer = preg_replace('/\[\^(\d+)\^\]/', '[$1]', $this->response['answer']);

        $adaptive_cards = trim(preg_replace('/\n\n(.|\n)*$/', '', $adaptive_cards));

        if($adaptive_cards == $answer){
            $adaptive_cards = '';
        }

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
            $text .= $answer . PHP_EOL;
        }

        if ($text) {
            $text .= PHP_EOL;
        }

        $text .= $adaptive_cards;

        Log::info($text);

        Redis::connection()->client()->set('last_message_answer:'.$this->key, $text,['ex'=>3600]);

        if (!Redis::connection()->client()->set('nums:' . $this->key, 1, ['nx', 'ex' => 5])) {
            return true;
        }

        go:

        $last_message_id = Redis::client()->get('last_message_id:' . $this->key);

        if (!$last_message_id) {
            $data = TelegramService::sendTelegram($text, $this->chat_id);

            $last_message_id = $data['data']['result']['message_id'] ?? 0;

            Redis::connection()->client()->set('last_message_id:' . $this->key, $last_message_id, ['nx', 'ex' => 3600]);
        } else {
            $data = TelegramService::updateTelegram($text, $this->chat_id, $last_message_id);
        }

        if (!$this->return_redis){
            //5秒之后再次执行
            dispatch(new Progress($this->bot_name, $this->bot_token, $this->chat_id, $this->response, $this->key,true))->delay(now()->addSeconds(5));
        }

//        Log::info('error:' . $last_message_id, $data);

        return $data;
    }
}
