<?php

namespace App\Http\Services;

use App\Models\ChatConversations;
use App\Models\TelegramChat;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Log;

class SiriService extends BaseService
{
    private static string $bot_name;

    public function __construct()
    {
        self::$bot_name = 'siri_001';

        parent::__construct();
    }

    public function siri($siri_id, $text, $token = '',$system = '')
    {
        $chat_id = TelegramChat::getLastChatId($siri_id, self::$bot_name, true);

        $system = $system?:'可靠的生活小助手，耐心，会非常详细的回答我的问题';

        if (!$chat_id) {
            $gpt     = ChatConversations::record(getUuid(), getUuid());
            $chat_id = $gpt->id;
        }

        $bot_name = self::$bot_name;

        $chat = new TelegramChat();

        try {
            if (preg_match('/^ok$/i', $text)) {
                //手动结束对话

                TelegramChat::where([
                    'telegram_chat_id' => $siri_id,
                    'username'         => self::$bot_name,
                    'recycle'          => 0,
                ])->update(['recycle' => 1]);

                return '已手动结束本轮对话';
            }

            $json = GptService::getInstance()->gpt3($system, $chat_id, $text, $token);

            if(isset($json['error']['message'])){
                Log::info(self::$bot_name . ': ' . $json['error']['message']);
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
                return $json['choices'][0]['message']['content'] . PHP_EOL . '剩余token数不足1000，可回复ok重置会话';
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
}
