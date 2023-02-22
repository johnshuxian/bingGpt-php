<?php

namespace App\Http\Services;

use App\Models\TelegramChat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService extends BaseService
{
    public function getFile($file_id)
    {
        try {
            $answer = [
                'file_id' => $file_id,
            ];

            $response = Http::withoutVerifying()->acceptJson()->timeout(5)->post('https://api.telegram.org/' . env('TELEGRAM_BOT_TOKEN') . '/getFile', $answer);

            return ['code' => 1, 'message' => '', 'data' => $response->json()];
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return ['code' => 0, 'message' => $e->getMessage()];
        }
    }

    public static function sendTelegram($content, $telegram_chat_id, $type = 'text', $prompt = '点击查看'): array
    {
        try {
            $answer = [
                $type     => $content,
                'chat_id' => $telegram_chat_id,
            ];

            $method = [
                'text'  => 'sendMessage',
                'photo' => 'sendPhoto',
            ];

            if ('photo' == $type) {
                $answer['caption'] = $prompt;
            }

            $response = Http::acceptJson()->withoutVerifying()->timeout(5)->post('https://api.telegram.org/' . env('TELEGRAM_BOT_TOKEN') . '/' . $method[$type], $answer);

            return ['code' => 1, 'message' => '', 'data' => $response->json()];
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            Log::info($e->getResponse()->getBody()->getContents());

            return ['code' => 0, 'message' => $e->getMessage()];
        }
    }

    public function telegram(array $params)
    {
        $bot_name = env('TELEGRAM_BOT_NAME');

        $chat = new TelegramChat();

        if (!isset($params['message']['text'])) {
            $text = '我支持回复文字消息哦';
        } else {
            if ('private' == $params['message']['chat']['type'] || ($params['message']['reply_to_message']['from']['username'] ?? '') == $bot_name || (preg_match("/@{$bot_name}/", $params['message']['text']))) {
                $text = $params['message']['text'];

                $text = trim(preg_replace("/@{$bot_name}/", '', $text));

                if (preg_match('/^ok$/i', $text)) {
                    //手动结束对话

                    TelegramChat::where([
                        'telegram_chat_id' => $params['message']['chat']['id'],
                        'recycle'          => 0,
                    ])->update(['recycle' => 1]);

                    return self::sendTelegram('已手动结束本轮对话', $params['message']['chat']['id']);
                }

                $chat_id = TelegramChat::getLastChatId($params['message']['chat']['id']);

                if (!$chat_id) {
                    $json = BingGptService::getInstance()->createConversation(true);

                    if (!$json['code']) {
                        return self::sendTelegram($json['error'], $params['message']['chat']['id']);
                    }

                    $chat_id = $json['data']['chatId'];
                }

                $username = $params['message']['from']['username'] ?? ($params['message']['from']['first_name'] . '-' . $params['message']['from']['last_name']);

                $chat->record(
                    $username,
                    $text,
                    $params['message']['chat']['id'],
                    $chat_id,
                    $params['message']['chat']['type'],
                    $params['message']['from']['is_bot']
                );

                $json = BingGptService::getInstance()->ask($text, $chat_id, true);

                if ($json['code']) {
                    foreach (['answer', 'adaptive_cards'] as $key) {
                        $text = $json['data'][$key];

                        if ('adaptive_cards' == $key && $json['data'][$key] == $json['data']['answer']) {
                            return 200;
                        }
                        $chat->record(
                            $bot_name,
                            $text,
                            $params['message']['chat']['id'],
                            $chat_id,
                            $params['message']['chat']['type'],
                            true
                        );

                        self::sendTelegram(preg_replace('/\[\^(\d+)\^\]/', '[$1]', $text), $params['message']['chat']['id']);
                    }
                }
            }
        }

        return 200;
    }
}
