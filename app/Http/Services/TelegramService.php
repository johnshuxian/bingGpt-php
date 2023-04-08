<?php

namespace App\Http\Services;

use App\Models\ChatConversations;
use App\Models\TelegramChat;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class TelegramService extends BaseService
{
    /**
     * @var mixed|string
     */
    public static mixed $bot_token;

    /**
     * @var mixed|string
     */
    public static mixed $bot_name;
    public static int $last_message_id = 0;
    public static $chat_id             = '';

    /**
     * @var mixed|string
     */
    public static mixed $key;

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

    public static function sendTelegram($content, $telegram_chat_id, $type = 'text', $prompt = '点击查看', $replyInlineMarkup = []): array
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
            // 转译telegram特殊字符
            //                $answer[$type] = '```' . PHP_EOL . str_replace(['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'], ['\\_', '\\*', '\\[', '\\]', '\\(', '\\)', '\\~', '\\`', '\\>', '\\#', '\\+', '\\-', '\\=', '\\|', '\\{', '\\}', '\\.', '\\!'], $answer[$type]) . PHP_EOL . '```';
            //                $answer[$type] = '```' . $answer[$type] . PHP_EOL . '```';
            //
            //                $answer['parse_mode'] = 'MarkdownV2';

            if (!empty($replyInlineMarkup)) {
                $answer['reply_markup'] = $replyInlineMarkup;

                //                Log::info('debug:', $answer);
            }

            $response = Http::acceptJson()->withoutVerifying()->timeout(10)->post('https://api.telegram.org/' . self::$bot_token . '/' . $method[$type], $answer);

            //            Log::info($response);

            return ['code' => 1, 'message' => '', 'data' => $response->json()];
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            Log::info($e->getResponse()->getBody()->getContents());

            return ['code' => 0, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            Log::info($e->getMessage());

            return ['code' => 0, 'message' => $e->getMessage()];
        }
    }

    public static function updateTelegram($content, $telegram_chat_id, $message_id, $replyInlineMarkup = []): array
    {
        try {
            $answer = [
                'chat_id'    => $telegram_chat_id,
                'message_id' => $message_id,
                'text'       => $content,
            ];

            // 转译telegram特殊字符
            //            $answer['text'] = '```' . PHP_EOL . str_replace(['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'], ['\\_', '\\*', '\\[', '\\]', '\\(', '\\)', '\\~', '\\`', '\\>', '\\#', '\\+', '\\-', '\\=', '\\|', '\\{', '\\}', '\\.', '\\!'], $answer['text']) . PHP_EOL . '```';
            //            $answer['text'] = '```' . $answer['text'] . PHP_EOL . '```';

            //            $answer['parse_mode'] = 'MarkdownV2';

            if (!empty($replyInlineMarkup)) {
                $answer['reply_markup'] = $replyInlineMarkup;

                //                Log::info('debug:', $answer);
            }

            $response = Http::acceptJson()->withoutVerifying()->timeout(10)->post('https://api.telegram.org/' . self::$bot_token . '/editMessageText', $answer);

            //            Log::info($response);

            return ['code' => 1, 'message' => '', 'data' => $response->json()];
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            Log::info($e->getResponse()->getBody()->getContents());

            return ['code' => 0, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            Log::info($e->getMessage());

            return ['code' => 0, 'message' => $e->getMessage()];
        }
    }

    public function telegram(array $params, $method = 'bingGpt', $token='', $name = '', $key = '')
    {
        self::bot($token, $name);

        self::$key = $key;

        self::$last_message_id = 0;

        Log::info('info: ', ['token' => self::$bot_token, 'name' => self::$bot_name, 'key' => self::$key, 'last_message_id' => self::$last_message_id]);

        return self::$method($params);
    }

    private static function bingGpt($params)
    {
        $bot_name = self::$bot_name;

        $chat = new TelegramChat();

        if (!isset($params['message']['text'])) {
            $text = '我支持回复文字消息哦';
        } else {
            if ('private' == $params['message']['chat']['type'] || ($params['message']['reply_to_message']['from']['username'] ?? '') == $bot_name || preg_match("/@{$bot_name}/", $params['message']['text'])) {
                $text = $params['message']['text'];

                $text = trim(preg_replace("/@{$bot_name}/", '', $text));

                if (preg_match('/^ok$/i', $text)) {
                    // 手动结束对话

                    TelegramChat::where([
                        'telegram_chat_id' => $params['message']['chat']['id'],
                        'username'         => self::$bot_name,
                        'recycle'          => 0,
                    ])->update(['recycle' => 1]);

                    return self::sendTelegram('已手动结束本轮对话', $params['message']['chat']['id']);
                }

                $chat_id = TelegramChat::getLastChatId($params['message']['chat']['id'], self::$bot_name, true, 'bing_id');

                if (!$chat_id) {
                    $json = BingGptService::getInstance()->createConversation(true);

                    if (!$json['code']) {
                        return self::sendTelegram($json['message'], $params['message']['chat']['id']);
                    }

                    $chat_id = $json['data']['chatId'];
                }

                $username = $params['message']['from']['username'] ?? ($params['message']['from']['first_name'] . '-' . $params['message']['from']['last_name']);

                // ['telegram_chat_id'=>$telegram_chat_id,'chat_id'=>$chat_id,'chat_type'=>$chat_type,'content'=>$content,'username'=>$username,'is_bot'=>$is_bot?1:0]
                // $username,$content,$telegram_chat_id,$chat_id = 0,$chat_type = 'private',$is_bot = false
                $chat->record([
                    'username'         => $username,
                    'content'          => $text,
                    'telegram_chat_id' => $params['message']['chat']['id'],
                    'bing_id'          => $chat_id,
                    'chat_type'        => $params['message']['chat']['type'],
                    'is_bot'           => $params['message']['from']['is_bot'] ? 1 : 0,
                ]);

                self::$chat_id = $params['message']['chat']['id'];

                $json = BingGptService::getInstance()->ask($text, $chat_id, true);

                if ($json['code']) {
                    Log::info($username . ': ' . $text);

                    $answer = preg_replace('/\[\^(\d+)\^\]/', '[$1]', $json['data']['answer']);

                    $text = '';

                    if ($json['data']['numUserMessagesInConversation']) {
                        $text .= '(' . $json['data']['numUserMessagesInConversation'] . '/' . $json['data']['maxNumUserMessagesInConversation'] . ')--生成结束';
                    }

                    if ($text) {
                        $text .= PHP_EOL;
                    }

                    if ($answer) {
                        $text .= $answer . PHP_EOL;
                    } else {
                        $text .= '无返回内容，也许你得换个话题' . PHP_EOL;
                    }

                    Log::info(self::$bot_name . ': ' . $text);

                    $chat->record([
                        'username'         => self::$bot_name,
                        'content'          => $text,
                        'telegram_chat_id' => $params['message']['chat']['id'],
                        'bing_id'          => $chat_id,
                        'chat_type'        => $params['message']['chat']['type'],
                        'is_bot'           => 1,
                    ]);

                    return self::sendOrUpdate($text, 'text', $json['data']['adaptive_cards']);
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
            if ('private' == $params['message']['chat']['type'] || ($params['message']['reply_to_message']['from']['username'] ?? '') == $bot_name || preg_match("/@{$bot_name}/", $params['message']['text'])) {
                $text = $params['message']['text'];

                $text = trim(preg_replace("/@{$bot_name}/", '', $text));

                $username = $params['message']['from']['username'] ?? ($params['message']['from']['first_name'] . '-' . $params['message']['from']['last_name']);

                ChatGptService::getInstance()->addAccount(config('chat_gpt.access_token'));

                $chat_id = TelegramChat::getLastChatId($params['message']['chat']['id'], self::$bot_name, false);

                $conversation_id = null;

                if ($chat_id) {
                    $gpt             = ChatConversations::find($chat_id);
                    $conversation_id = $gpt->conversation_id ?? '';
                }

                self::$chat_id = $params['message']['chat']['id'];

                try {
                    if (preg_match('/^ok$/i', $text) && $conversation_id) {
                        // 手动结束对话
                        TelegramChat::where([
                            'telegram_chat_id' => $params['message']['chat']['id'],
                            'username'         => self::$bot_name,
                            'recycle'          => 0,
                        ])->update(['recycle' => 1]);

                        self::sendOrUpdate('已手动结束本轮对话');

                        ChatGptService::getInstance()->deleteConversation($conversation_id);

                        return true;
                    }

                    //                    self::sendOrUpdate('tips: 若长时间没有回复，请重新询问' . PHP_EOL . '稍等，回答正在生成中...');

                    $answer = [];

                    foreach (ChatGptService::getInstance()->ask($text, $conversation_id, null, null, true) as $value) {
                        $answer = $value;

                        if ($answer['answer'] && Redis::connection()->client()->set('chat_gpt_update:' . self::$key, 1, ['nx', 'ex' => 5])) {
                            self::sendOrUpdate($answer['answer']);
                        }
                    }

                    $gpt = 0;

                    if (isset($answer['conversation_id'])) {
                        $gpt = ChatConversations::record($answer['conversation_id'], $answer['id']);

                        $gpt = $gpt->id;
                    }

                    $chat->record([
                        'username'         => $username,
                        'content'          => $text,
                        'telegram_chat_id' => $params['message']['chat']['id'],
                        'chat_id'          => $gpt,
                        'chat_type'        => $params['message']['chat']['type'],
                        'is_bot'           => $params['message']['from']['is_bot'] ? 1 : 0,
                    ]);

                    $chat->record([
                        'username'         => self::$bot_name,
                        'content'          => $answer['answer'],
                        'telegram_chat_id' => $params['message']['chat']['id'],
                        'chat_id'          => $gpt,
                        'chat_type'        => $params['message']['chat']['type'],
                        'is_bot'           => 1,
                    ]);
                    Log::info($username . ': ' . $text);

                    Log::info(self::$bot_name . ': ' . $answer['answer']);

                    //                    return self::sendOrUpdate($json['response']);
                    return self::sendOrUpdate($answer['answer']);
                } catch (BadResponseException $exception) {
                    //                    if (isset($arr['conversation_id'])) {
                    //                        $response = Http::acceptJson()->timeout(300)->get('http://127.0.0.1:8000/delete', [
                    //                            'conversation_id'=> $arr['conversation_id'],
                    //                        ]);
                    //
                    //                        TelegramChat::where([
                    //                            'telegram_chat_id' => $params['message']['chat']['id'],
                    //                            'recycle'          => 0,
                    //                        ])->update(['recycle' => 1]);
                    //                    }

                    //                    return self::sendOrUpdate($exception->getResponse()->getBody());
                    return self::sendOrUpdate($exception->getResponse()->getBody() . ' in ' . $exception->getFile() . ' at ' . $exception->getLine());
                } catch (\Exception $exception) {
                    Log::info($exception->getMessage() . ' in ' . $exception->getFile() . ' at ' . $exception->getLine());
                    Log::info('info:', $answer ?? []);
                    //                    if (isset($arr['conversation_id'])) {
                    //                        $response = Http::acceptJson()->timeout(300)->get('http://127.0.0.1:8000/delete', [
                    //                            'conversation_id'=> $arr['conversation_id'],
                    //                        ]);
                    //
                    //                        TelegramChat::where([
                    //                            'telegram_chat_id' => $params['message']['chat']['id'],
                    //                            'recycle'          => 0,
                    //                        ])->update(['recycle' => 1]);
                    //                    }

                    //                    return self::sendOrUpdate($exception->getMessage());
                    return self::sendOrUpdate($exception->getMessage() . ' in ' . $exception->getFile() . ' at ' . $exception->getLine());
                }
            }
        }

        return 200;
    }

    private static function gpt3(array $params)
    {
        $bot_name = self::$bot_name;

        $chat = new TelegramChat();

        if (!isset($params['message']['text'])) {
            $text = '我支持回复文字消息哦';
        } else {
            if ('private' == $params['message']['chat']['type'] || ($params['message']['reply_to_message']['from']['username'] ?? '') == $bot_name || preg_match("/@{$bot_name}/", $params['message']['text'])) {
                $text = $params['message']['text'];

                $text = trim(preg_replace("/@{$bot_name}/", '', $text));

                $username = $params['message']['from']['username'] ?? ($params['message']['from']['first_name'] . '-' . $params['message']['from']['last_name']);

                $chat_id = TelegramChat::getLastChatId($params['message']['chat']['id'], self::$bot_name, true);

                if (!$chat_id) {
                    $gpt     = ChatConversations::record(getUuid(), getUuid());
                    $chat_id = $gpt->id;
                }

                try {
                    if (preg_match('/^ok$/i', $text)) {
                        // 手动结束对话

                        TelegramChat::where([
                            'telegram_chat_id' => $params['message']['chat']['id'],
                            'username'         => self::$bot_name,
                            'recycle'          => 0,
                        ])->update(['recycle' => 1]);

                        return self::sendTelegram('已手动结束本轮对话', $params['message']['chat']['id']);
                    }

                    $json = GptService::getInstance()->gpt3('You are very helpful, knowledgeable, and also a great English translator. You tackle our questions with patience and provide detailed explanations, making you a master of English translation.', $chat_id, $text);

                    if (0 == $json['code']) {
                        return self::sendTelegram($json['answer'], $params['message']['chat']['id']);
                    }

                    $chat->record([
                        'username'         => $username,
                        'content'          => $text,
                        'telegram_chat_id' => $params['message']['chat']['id'],
                        'chat_id'          => $chat_id,
                        'chat_type'        => $params['message']['chat']['type'],
                        'is_bot'           => $params['message']['from']['is_bot'] ? 1 : 0,
                    ]);

                    $chat->record([
                        'username'         => self::$bot_name,
                        'content'          => $json['answer'],
                        'telegram_chat_id' => $params['message']['chat']['id'],
                        'chat_id'          => $chat_id,
                        'chat_type'        => $params['message']['chat']['type'],
                        'is_bot'           => 1,
                    ]);
                    Log::info($username . ': ' . $text);

                    Log::info(self::$bot_name . ': ' . $json['answer']);

                    $tokens = $json['total_tokens'];

                    $text = '该轮会话剩余可用字数: ' . (4096 - $tokens) . PHP_EOL . $json['answer'];

                    self::sendTelegram($text, $params['message']['chat']['id']);
                } catch (BadResponseException $exception) {
                    self::sendTelegram($exception->getResponse()->getBody(), $params['message']['chat']['id']);
                } catch (\Exception $exception) {
                    Log::info($exception->getMessage() . ' in ' . $exception->getFile() . ' at ' . $exception->getLine());
                    Log::info('info:', $json ?? []);

                    self::sendTelegram($exception->getMessage(), $params['message']['chat']['id']);
                }

                return 200;
            }
        }

        return 200;
    }

    public static function sendOrUpdate($content, $type = 'text', $adaptive_cards = [])
    {
        $last_message_id = Redis::connection()->client()->get('last_message_id:' . self::$key);

        if ($last_message_id) {
            self::$last_message_id = $last_message_id;
        }

        if (!self::$last_message_id) {
            $data = self::sendTelegram($content, self::$chat_id, $type, $adaptive_cards);

            self::$last_message_id = $data['data']['result']['message_id'] ?? 0;

            Redis::connection()->client()->set('last_message_id:' . self::$key, self::$last_message_id, ['ex' => 3600]);
        } else {
            $data = self::updateTelegram($content, self::$chat_id, self::$last_message_id, $adaptive_cards);
        }

        Log::info('sendOrUpdate:', $data);

        return $data;
    }
}
