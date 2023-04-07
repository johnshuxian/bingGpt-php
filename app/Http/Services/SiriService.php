<?php

namespace App\Http\Services;

use App\Jobs\Send;
use App\Models\ChatConversations;
use App\Models\TelegramChat;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Log;

class SiriService extends BaseService
{
    private static string $bot_name;

    public function __construct()
    {
        parent::__construct();
    }

    public function siri($siri_id, $text, $token = '', $system = '')
    {
        self::$bot_name = 'siri_001';

        if (preg_match('/^ok$/i', $text)) {
            //手动结束对话

            TelegramChat::where([
                'telegram_chat_id' => $siri_id,
                'username'         => self::$bot_name,
                'recycle'          => 0,
            ])->update(['recycle' => 1]);

            return '已手动结束本轮对话';
        }

        $chat_id = TelegramChat::getLastChatId($siri_id, self::$bot_name, true);

        $system = $system ?: '可靠的生活小助手，耐心，会非常详细的回答我的问题';

        if (!$chat_id) {
            $gpt     = ChatConversations::record(getUuid(), getUuid());
            $chat_id = $gpt->id;
        }

        $bot_name = self::$bot_name;

        $chat = new TelegramChat();

        try {
            $json = GptService::getInstance()->gpt3($system, $chat_id, $text, $token);

            if (isset($json['error']['message'])) {
                Log::info(self::$bot_name . ': ' . $json['error']['message']);

                if (config('telegram.siri')[$siri_id]) {
                    dispatch(new Send(env('TELEGRAM_BOT_NAME_2'), env('TELEGRAM_BOT_TOKEN_2'), config('telegram.siri')[$siri_id], 'you：' . $text . PHP_EOL . 'siri：' . $json['error']['message']));
                }

                return $json['error']['message'];
            }

            $chat->record([
                'username'        => 'johns',
                'content'         => $text,
                'telegram_chat_id'=> $siri_id,
                'chat_id'         => $chat_id,
                'chat_type'       => 'private',
                'is_bot'          => 0,
            ]);

            $chat->record([
                'username'        => self::$bot_name,
                'content'         => $json['choices'][0]['message']['content'],
                'telegram_chat_id'=> $siri_id,
                'chat_id'         => $chat_id,
                'chat_type'       => 'private',
                'is_bot'          => 1,
            ]);
            Log::info('johns: ' . $text);

            Log::info(self::$bot_name . ': ' . $json['choices'][0]['message']['content']);

            $tokens = $json['usage']['total_tokens'];

            if (4096 - $tokens <= 1000) {
                $answer = $json['choices'][0]['message']['content'] . PHP_EOL . '剩余token数' . (4096 - $tokens) . '，可回复ok重置会话';

                if (config('telegram.siri')[$siri_id]) {
                    dispatch(new Send(env('TELEGRAM_BOT_NAME_2'), env('TELEGRAM_BOT_TOKEN_2'), config('telegram.siri')[$siri_id], 'you：' . $text . PHP_EOL . 'siri：' . $answer));
                }

                return $answer;
            }

            if (config('telegram.siri')[$siri_id]) {
                dispatch(new Send(env('TELEGRAM_BOT_NAME_2'), env('TELEGRAM_BOT_TOKEN_2'), config('telegram.siri')[$siri_id], 'you：' . $text . PHP_EOL . 'siri：' . $json['choices'][0]['message']['content']));
            }

            return $json['choices'][0]['message']['content'];
        } catch (BadResponseException $exception) {
            return $exception->getResponse()->getBody();
        } catch (\Exception $exception) {
            Log::info($exception->getMessage() . ' in ' . $exception->getFile() . ' at ' . $exception->getLine());
            Log::info('info:', $json ?? []);

            return $exception->getMessage();
        }
    }

    public function bing($siri_id, $text, $token = '', $system = '')
    {
        self::$bot_name = 'siri_002';

        if (preg_match('/^ok$/i', $text)) {
            //手动结束对话

            TelegramChat::where([
                'telegram_chat_id' => $siri_id,
                'username'         => self::$bot_name,
                'recycle'          => 0,
            ])->update(['recycle' => 1]);

            return '已手动结束本轮对话';
        }

        $chat_id = TelegramChat::getLastChatId($siri_id, self::$bot_name, true, 'bing_id');

        if (!$chat_id) {
            $json = BingGptService::getInstance()->createConversation(true);

            Log::info('bing createConversation: ' . json_encode($json));

            if (!$json['code']) {
                return $json['message'];
            }

            $chat_id = $json['data']['chatId'];
        }

        $bot_name = self::$bot_name;

        $chat = new TelegramChat();

        try {
            $json = BingGptService::getInstance()->ask($text, $chat_id, true, true);

            if($json['code'] == 1){
                //成功
                $answer = $json['data']['answer'];
            }else{
                //失败
                $answer = $json['message'];
            }

            $chat->record([
                'username'        => 'johns',
                'content'         => $text,
                'telegram_chat_id'=> $siri_id,
                'bing_id'         => $chat_id,
                'chat_type'       => 'private',
                'is_bot'          => 0,
            ]);

            $chat->record([
                'username'        => self::$bot_name,
                'content'         => $answer,
                'telegram_chat_id'=> $siri_id,
                'bing_id'         => $chat_id,
                'chat_type'       => 'private',
                'is_bot'          => 1,
            ]);

            Log::info('johns: ' . $text);

            Log::info(self::$bot_name . ': ' . $answer);

            $answer = preg_replace('/\[\^(\d+)\^\]/', '', $answer);

            if ($json['code'] == 1 && $json['data']['numUserMessagesInConversation'] != 0 && $json['data']['numUserMessagesInConversation'] == $json['data']['maxNumUserMessagesInConversation']) {
                $answer = $answer . '，回合数已用完，已自动重置';

                TelegramChat::where([
                    'telegram_chat_id' => $siri_id,
                    'username'         => self::$bot_name,
                    'recycle'          => 0,
                ])->update(['recycle' => 1]);
            }

            if (config('telegram.siri')[$siri_id]) {
                dispatch(new Send(env('TELEGRAM_BOT_NAME_1'), env('TELEGRAM_BOT_TOKEN_1'), config('telegram.siri')[$siri_id], 'you：' . $text . PHP_EOL . 'siri：' . $answer));
            }

            return $answer;
        } catch (BadResponseException $exception) {
            return $exception->getResponse()->getBody();
        } catch (\Exception $exception) {
            Log::info($exception->getMessage() . ' in ' . $exception->getFile() . ' at ' . $exception->getLine());
            Log::info('info:', $json ?? []);

            return $exception->getMessage();
        }
    }

    public function ask($text, $token, $system)
    {
        $json = GptService::getInstance()->gpt3($system, 0, $text, $token);

        if (isset($json['error']['message'])) {
            Log::info('info:' . $json['error']['message']);

            return [0, $json['error']['message']];
        }

        return [1, $json['choices'][0]['message']['content']];
    }
}
