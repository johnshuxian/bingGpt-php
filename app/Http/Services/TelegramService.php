<?php

namespace App\Http\Services;

use App\Models\ChatConversations;
use App\Models\TelegramChat;
use GuzzleHttp\Exception\BadResponseException;
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
        } catch (\Exception $e) {
            Log::info($e->getMessage());

            return ['code' => 0, 'message' => $e->getMessage()];
        }
    }

    public function telegram(array $params, $method = 'bingGpt')
    {
        return self::$method($params);
    }

    private static function bingGpt($params)
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
                        return self::sendTelegram($json['message'], $params['message']['chat']['id']);
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

                self::sendTelegram($json['message'], $params['message']['chat']['id']);
            }
        }

        return 200;
    }

    private static function chatGpt(array $params)
    {
        $bot_name = env('TELEGRAM_BOT_NAME');

        $chat = new TelegramChat();

        if (!isset($params['message']['text'])) {
            $text = '我支持回复文字消息哦';
        } else {
            if ('private' == $params['message']['chat']['type'] || ($params['message']['reply_to_message']['from']['username'] ?? '') == $bot_name || (preg_match("/@{$bot_name}/", $params['message']['text']))) {
                $text = $params['message']['text'];

                $text = trim(preg_replace("/@{$bot_name}/", '', $text));

                $username = $params['message']['from']['username'] ?? ($params['message']['from']['first_name'] . '-' . $params['message']['from']['last_name']);

                $chat_id = TelegramChat::getLastChatId($params['message']['chat']['id'], false);

                $arr = [
                    'prompt'=> $text,
                ];

                if ($chat_id) {
                    $gpt = ChatConversations::select('conversation_id', 'parent_id')->where('id', $chat_id)->first();

                    if ($gpt) {
                        $arr = array_merge($arr, $gpt->toArray());
                    } else {
                        $chat_id = 0;
                    }
                }

                try {
                    $response = Http::acceptJson()->timeout(300)->get('http://127.0.0.1:8000/ask', $arr);

                    $json = $response->json();

                    $gpt = 0;

                    if (isset($json['data']['conversation_id'])) {
                        $gpt = ChatConversations::record($json['data']['conversation_id'], $json['data']['parent_id'] ?? 0);

                        $gpt = $gpt->id;
                    }

                    $chat->record(
                        $username,
                        $text,
                        $params['message']['chat']['id'],
                        $gpt,
                        $params['message']['chat']['type'],
                        $params['message']['from']['is_bot']
                    );

                    $chat->record(
                        $bot_name,
                        $json['response'],
                        $params['message']['chat']['id'],
                        $gpt,
                        $params['message']['chat']['type'],
                        true
                    );

                    self::sendTelegram($json['response'], $params['message']['chat']['id']);
                } catch (BadResponseException $exception) {
                    self::sendTelegram($exception->getResponse()->getBody(), $params['message']['chat']['id']);
                } catch (\Exception $exception) {
                    self::sendTelegram($exception->getMessage(), $params['message']['chat']['id']);
                }

                return 200;
            }
        }

        return 200;
    }
}