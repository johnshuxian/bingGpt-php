<?php

namespace App\Http\Services;

use App\Models\ChatConversations;
use App\Models\TelegramChat;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService extends BaseService
{
    /**
     * @var mixed|string
     */
    private static mixed $bot_token;
    /**
     * @var mixed|string
     */
    private static mixed $bot_name;
    public static int $last_message_id = 0;
    private static $chat_id            = '';

    public static function bot($token = '', $name = '')
    {
        self::$bot_token = $token ?: env('TELEGRAM_BOT_TOKEN');
        self::$bot_name  = $name ?: env('TELEGRAM_BOT_NAME');
    }

    public function getFile($file_id)
    {
        try {
            $answer = [
                'file_id' => $file_id,
            ];

            $response = Http::withoutVerifying()->acceptJson()->timeout(5)->post('https://api.telegram.org/' . self::$bot_token . '/getFile', $answer);

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

            $response = Http::acceptJson()->withoutVerifying()->timeout(5)->post('https://api.telegram.org/' . self::$bot_token . '/' . $method[$type], $answer);

            return ['code' => 1, 'message' => '', 'data' => $response->json()];
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            Log::info($e->getResponse()->getBody()->getContents());

            return ['code' => 0, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            Log::info($e->getMessage());

            return ['code' => 0, 'message' => $e->getMessage()];
        }
    }

    public static function updateTelegram($content, $telegram_chat_id, $message_id): array
    {
        try {
            $answer = [
                'chat_id'   => $telegram_chat_id,
                'message_id'=> $message_id,
                'text'      => $content,
            ];

            $response = Http::acceptJson()->withoutVerifying()->timeout(5)->post('https://api.telegram.org/' . self::$bot_token . '/editMessageText', $answer);

            return ['code' => 1, 'message' => '', 'data' => $response->json()];
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            Log::info($e->getResponse()->getBody()->getContents());

            return ['code' => 0, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            Log::info($e->getMessage());

            return ['code' => 0, 'message' => $e->getMessage()];
        }
    }

    public function telegram(array $params, $method = 'bingGpt', $token='', $name = '')
    {
        self::bot($token, $name);

//        Log::info('info: ', ['token'=>self::$bot_token, 'name'=>self::$bot_name]);

        return self::$method($params);
    }

    private static function bingGpt($params)
    {
        $bot_name = self::$bot_name;

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
                        'username'         => self::$bot_name,
                        'recycle'          => 0,
                    ])->update(['recycle' => 1]);

                    return self::sendTelegram('已手动结束本轮对话', $params['message']['chat']['id']);
                }

                $chat_id = TelegramChat::getLastChatId($params['message']['chat']['id'], self::$bot_name);

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

                self::$chat_id = $params['message']['chat']['id'];

                $json = BingGptService::getInstance()->ask($text, $chat_id, true);

                if ($json['code']) {
                    Log::info($username . ': ' . $text);

                    if ($json['data']['answer'] == $json['data']['adaptive_cards']) {
                        $json['data']['adaptive_cards'] = '';
                    }

                    $json['data']['adaptive_cards'] = str_replace($json['data']['answer'], '', $json['data']['adaptive_cards']);

                    $text = preg_replace('/\[\^(\d+)\^\]/', '[$1]', $json['data']['answer']) . PHP_EOL . $json['data']['adaptive_cards'];

                    Log::info(self::$bot_name . ': ' . $text);

                    $chat->record(
                        self::$bot_name,
                        $text,
                        $params['message']['chat']['id'],
                        $chat_id,
                        $params['message']['chat']['type'],
                        true
                    );

                    return self::sendOrUpdate(preg_replace('/\[\^(\d+)\^\]/', '[$1]', $text));
//                    return self::sendTelegram(preg_replace('/\[\^(\d+)\^\]/', '[$1]', $text), $params['message']['chat']['id']);
                }

                return self::sendTelegram($json['message'] ?? '', $params['message']['chat']['id']);
            }
        }

        return 200;
    }

    private static function chatGpt(array $params)
    {
        $bot_name = self::$bot_name;

        $chat = new TelegramChat();

        if (!isset($params['message']['text'])) {
            $text = '我支持回复文字消息哦';
        } else {
            if ('private' == $params['message']['chat']['type'] || ($params['message']['reply_to_message']['from']['username'] ?? '') == $bot_name || (preg_match("/@{$bot_name}/", $params['message']['text']))) {
                $text = $params['message']['text'];

                $text = trim(preg_replace("/@{$bot_name}/", '', $text));

                $username = $params['message']['from']['username'] ?? ($params['message']['from']['first_name'] . '-' . $params['message']['from']['last_name']);

                $chat_id = TelegramChat::getLastChatId($params['message']['chat']['id'], self::$bot_name, false);

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
                    if (preg_match('/^ok$/i', $text) && isset($arr['conversation_id'])) {
                        //手动结束对话

                        $response = Http::acceptJson()->timeout(300)->get('http://127.0.0.1:8000/delete', [
                            'conversation_id'=> $arr['conversation_id'],
                        ]);

                        TelegramChat::where([
                            'telegram_chat_id' => $params['message']['chat']['id'],
                            'username'         => self::$bot_name,
                            'recycle'          => 0,
                        ])->update(['recycle' => 1]);

                        return self::sendTelegram('已手动结束本轮对话', $params['message']['chat']['id']);
                    }

                    self::$chat_id = $params['message']['chat']['id'];

                    self::sendOrUpdate('稍等，回答正在生成中...'.PHP_EOL.'若长时间没有回复，可发送\'再发一次\'重新获取');

                    $response = Http::acceptJson()->timeout(300)->post('http://127.0.0.1:8000/ask', $arr);

                    $json = $response->json();

                    if (!isset($json['response'])) {
                        return self::sendTelegram($response->body(), $params['message']['chat']['id']);
                    }

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
                    Log::info($username . ': ' . $text);

                    Log::info(self::$bot_name . ': ' . $json['response']);

                    self::sendOrUpdate($json['response']);
//                    self::sendTelegram($json['response'], $params['message']['chat']['id']);
                } catch (BadResponseException $exception) {
                    if (isset($arr['conversation_id'])) {
                        $response = Http::acceptJson()->timeout(300)->get('http://127.0.0.1:8000/delete', [
                            'conversation_id'=> $arr['conversation_id'],
                        ]);

                        TelegramChat::where([
                            'telegram_chat_id' => $params['message']['chat']['id'],
                            'recycle'          => 0,
                        ])->update(['recycle' => 1]);
                    }

                    self::sendTelegram($exception->getResponse()->getBody(), $params['message']['chat']['id']);
                } catch (\Exception $exception) {
                    Log::info($exception->getMessage() . ' in ' . $exception->getFile() . ' at ' . $exception->getLine());
                    Log::info('info:', $json ?? []);
                    if (isset($arr['conversation_id'])) {
                        $response = Http::acceptJson()->timeout(300)->get('http://127.0.0.1:8000/delete', [
                            'conversation_id'=> $arr['conversation_id'],
                        ]);

                        TelegramChat::where([
                            'telegram_chat_id' => $params['message']['chat']['id'],
                            'recycle'          => 0,
                        ])->update(['recycle' => 1]);
                    }

                    self::sendTelegram($exception->getMessage(), $params['message']['chat']['id']);
                }

                return 200;
            }
        }

        return 200;
    }

    public static function sendOrUpdate($content, $type = 'text')
    {
        if (!self::$last_message_id) {
            $data = self::sendTelegram($content, self::$chat_id, $type);

            self::$last_message_id = $data['data']['result']['message_id'] ?? 0;
        } else {
            $data = self::updateTelegram($content, self::$chat_id, self::$last_message_id);
        }

        return $data;
    }
}
