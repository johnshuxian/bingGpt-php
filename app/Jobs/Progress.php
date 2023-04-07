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
     * @param mixed $return_redis
     */
    public function __construct($bot_name, $bot_token, $chat_id, $response, $key, $return_redis = false)
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

        //争夺锁
        $while = Redis::connection()->client()->set('loop:'.$this->key, 1, ['nx', 'ex' => 3600]);

        while($while){
            if (!Redis::connection()->client()->set('nums:' . $this->key, 1, ['nx', 'ex' => 5])) {
                sleep(0.1);
                continue;
            }

            $this->response = json_decode(Redis::connection()->client()->get('last_message_answer:' . $this->key), true);

            if(!$this->response){
                //释放锁
                Redis::connection()->client()->del('loop:'.$this->key);

                return [];
            }

            $answer = preg_replace('/\[\^(\d+)\^\]/', '[$1]', $this->response['answer']);

            if (!trim($answer)) {
                continue;
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

//            Log::info($text);

            $arr = [];

            foreach ($this->response['adaptive_cards'] as $key => $value) {
                $k = (int) $key + 1;

                $arr['inline_keyboard'][] = [
                    [
                        'text' => "[{$k}] " . $value['providerDisplayName'],
                        'url'  => $value['seeMoreUrl'],
                    ],
                ];
            }

            foreach ($this->response['suggested_responses'] as $key => $value) {
                $k = (int) $key + 1;

                $arr['inline_keyboard'][] = [
                    [
                        'text'                             => "[推测{$k}] " . $value,
                        'switch_inline_query_current_chat' => $value,
                    ],
                ];
            }

            $this->response['adaptive_cards'] = $arr;

            $last_message_id = Redis::client()->get('last_message_id:' . $this->key);

            Log::info($this->key . ':' . $last_message_id);

            if(!Redis::connection()->client()->exists('last_message_answer:' . $this->key)){
                return true;
            }

            if (!$last_message_id) {
                $data = TelegramService::sendTelegram($text, $this->chat_id, 'text', [], $this->response['adaptive_cards']);

                $last_message_id = $data['data']['result']['message_id'] ?? 0;

                Redis::connection()->client()->set('last_message_id:' . $this->key, $last_message_id, ['ex' => 3600]);
            } else {
                $data = TelegramService::updateTelegram($text, $this->chat_id, $last_message_id, $this->response['adaptive_cards']);
            }

            Log::info('error:' . $last_message_id, $data);
        }

        return true;
    }
}
