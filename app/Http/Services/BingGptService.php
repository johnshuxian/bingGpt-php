<?php

namespace App\Http\Services;

use App\Exceptions\BusinessException;
use App\Helpers\ResponseEnum;
use App\Jobs\Progress;
use App\Models\BingConversations;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use WebSocket\Client;

class BingGptService extends BaseService
{
    /**
     * @var false|mixed
     */
    private mixed $siri_use;
    private $max_try = 2;

    public function __construct()
    {
        parent::__construct();

        set_time_limit(0);
    }

    protected static $invocation_id = 0;

    protected static $conversation = [];

    private static function conversation($chat_id)
    {
        if ($info = BingConversations::find($chat_id)) {
            self::$conversation[$chat_id] = $info;

            self::$invocation_id = self::$conversation[$chat_id]->invocation_id;

            return self::$conversation[$chat_id];
        }

        throw new BusinessException(ResponseEnum::CLIENT_PARAMETER_ERROR, 'unavailable chat_id');
    }

    private function getHeaders($with_cookie = true)
    {
        $headers = config('bing.headers');

        if ($with_cookie) {
            $headers['cookie'] = config('bing.cookie');
        }

        return $headers;
    }

    private static function messageIdentifier(array|string $json): string
    {
        if (is_array($json)) {
            $json = json_encode($json, JSON_UNESCAPED_UNICODE);
        }

        return $json . "\x1e";
    }

    private static function updateWss($question, $chat_id)
    {
        $bing = config('bing');

        $conversation = self::conversation($chat_id);

        if (0 == self::$invocation_id) {
            $replace = ['tone', 'format', 'length'];

            $prompt = $bing['prompt'];

            $prompt = str_replace('%text', $question, $prompt);

            foreach ($replace as $item) {
                $prompt = str_replace('%' . $item, $bing[$item], $prompt);
            }
        } else {
            $prompt = $question;
        }

        $info = [
            'arguments'    => [
                [
                    'source'                => 'cib',
                    'optionsSets'           => [
                        'nlu_direct_response_filter',
                        'deepleo',
                        'disable_emoji_spoken_text',
                        //                        "responsible_ai_policy_235",
                        'enablemm',
                        //                        "h3imaginative",
                        'gencontentv3',
                        'serploc',
                        'dv3sugg',
                    ],
                    'allowedMessageTypes'   => [
                        'Chat',
                        'InternalSearchQuery',
                        'InternalSearchResult',
                        'Disengaged',
                        'InternalLoaderMessage',
                        'RenderCardRequest',
                        'AdsQuery',
                        'SemanticSerp',
                        'GenerateContentQuery',
                        'SearchQuery',
                    ],
                    'sliceIds'              => [
                        '321bic62up',
                        '321bic62',
                        'ssoverlap25',
                        'accrngcf',
                        'chk1cln',
                        'nofbkcf',
                        'revdv3tf3',
                        'sydnonputc',
                        'sydpayajaxlog',
                        '321sloc',
                        '324hlthmons0',
                        '324jbfv2s0',
                        'notigersc',
                        'udsdserlccf',
                        'udswebdesc2',
                        '329v3pwebtrunc',
                    ],
                    'traceId'               => getRanHex(),
                    'verbosity'             => 'verbose',
                    'isStartOfSession'      => 0 === self::$invocation_id,
                    'message'               => [
                        'author'      => 'user',
                        'inputMethod' => 'Keyboard',
                        'text'        => $prompt,
                        'messageType' => 'Chat',
                    ],
                    'conversationSignature' => $conversation->conversation_signature,
                    'participant'           => [
                        'id' => $conversation->client_id,
                    ],
                    'conversationId'        => $conversation->conversation_id,
                ],
            ],
            'invocationId' => (string) self::$invocation_id,
            'target'       => 'chat',
            'type'         => 4,
        ];

        //                   'h3imaginative',//富有创造性的
        //                   'harmonyv3',    //平衡的
        //                    'h3precise'    //更加精确

        $styles = [
            'A' => 'h3imaginative',
            'B' => 'harmonyv3',
            'C' => 'h3precise',
        ];

        $conversation_style = \request()->input('style', env('BING_STYLE', 'A'));

        if (in_array($conversation_style, array_keys($styles))) {
            $info['arguments'][0]['optionsSets'][] = $styles[$conversation_style];
        } else {
            $info['arguments'][0]['optionsSets'][] = $styles['A'];
        }

        Log::info('info:', $info);

        return $info;
    }

    public function connectWss(string $prompt, $chat_id, $return_array = false, $key = 0)
    {
        Log::info('第' . ($key + 1) . '次重试');

        $ping = $start = time();

        $context = stream_context_create();
        stream_context_set_option($context, 'ssl', 'verify_peer', false);
        stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

        try {
            $client = new Client('wss://sydney.bing.com/sydney/ChatHub', [
                'headers'       => config('bing.wss_headers'),
                'timeout'       => 300,
                'fragment_size' => 409600,
                'context'       => $context,
                //            'logger'       => Log::channel('daily'),
                //            'persistent'   => true,
            ]);

            $this->handshark($client, $prompt, $chat_id);
        } catch (\Exception $exception) {
            // 连接错误，重试

            Log::error($exception->getMessage());

            sleep(2);

            ++$key;

            if ($key <= $this->max_try) {
                Log::info('连接出错：' . $exception->getMessage());

                return $this->connectWss($prompt, $chat_id, $return_array, $key);
            }

            if ($return_array) {
                return ['code' => 0, 'message' => $exception->getMessage()];
            }

            return $this->fail(ResponseEnum::CLIENT_NOT_FOUND_HTTP_ERROR, $exception->getMessage());
        }

        $response = [
            'ask'                              => $prompt,
            'answer'                           => '请稍后，正在生成中...',
            'adaptive_cards'                   => [],
            'suggested_responses'              => [],
            'maxNumUserMessagesInConversation' => 0,
            'numUserMessagesInConversation'    => 0,
        ];

        if (!$this->siri_use) {
            Redis::connection()->client()->set('last_message_answer:' . TelegramService::$key, json_encode($response), ['ex' => 3600]);
            dispatch(new Progress(TelegramService::$bot_name, TelegramService::$bot_token, TelegramService::$chat_id, $response, TelegramService::$key));
        }

        Log::error('开始接收消息');

        $index = 0;

        while (true) {
            try {
                if (!$client->isConnected()) {
                    if (!$response['answer']) {
                        $client->close();
                        $this->handshark($client, $prompt, $chat_id);

                        continue;
                    }

                    BingConversations::where('id', $chat_id)->increment('invocation_id');

                    if ($return_array) {
                        return ['code' => 1, 'message' => '', 'data' => $response];
                    }

                    return $this->success($response);
                }

                $old = $info = $client->receive();

                $info = explode("\x1e", $info);

                $message = json_decode($info[0] ?? '', true);

                if (time() - $ping >= 30) {
                    $client->text(self::messageIdentifier(['type' => 6]));
                    $ping = time();
                }

                if (time() - $start >= 360) {
                    // 超时仍未返回，返回最近的type 1的内容

                    $client->close();

                    BingConversations::where('id', $chat_id)->increment('invocation_id');

                    if ($return_array) {
                        return ['code' => 1, 'message' => '', 'data' => $response];
                    }

                    return $this->success($response);
                }

                //                Log::info(json_encode($message));

                if ($message) {
                    if (isset($message['error'])) {
                        $client->close();

                        sleep(5);

                        ++$key;

                        if ($key <= $this->max_try) {
                            Log::info('返回报错：' . $message['error']);

                            return $this->connectWss('我没有收到，请重发一次', $chat_id, $return_array, $key);
                        }

                        if ($return_array) {
                            return ['code' => 0, 'message' => $message['error']];
                        }

                        return $this->fail(ResponseEnum::CLIENT_NOT_FOUND_HTTP_ERROR, $message['error']);
                    }

                    if (isset($message['type']) && 2 == $message['type']) {
                        if (!isset($message['item']['messages'])) {
                            $client->close();

                            sleep(5);

                            ++$key;

                            if ($key <= $this->max_try) {
                                Log::info('无正常返回值报错：' . $message['item']['result']['message']);

                                return $this->connectWss($prompt, $chat_id, $return_array, $key);
                            }

                            if ($return_array) {
                                return ['code' => 0, 'message' => $message['item']['result']['message']];
                            }

                            return $this->fail(ResponseEnum::CLIENT_NOT_FOUND_HTTP_ERROR, $message['item']['result']['message']);
                        }

                        foreach ($message['item']['messages'] as $answer) {
                            if (!isset($answer['messageType'])) {
                                if ('bot' == $answer['author']) {
                                    // 答案已生成
                                    if (0 == $response['maxNumUserMessagesInConversation']) {
                                        $response['maxNumUserMessagesInConversation'] = $message['item']['throttling']['maxNumUserMessagesInConversation'] ?? 0;
                                        $response['numUserMessagesInConversation']    = $message['item']['throttling']['numUserMessagesInConversation'] ?? 0;
                                    }

                                    $response['answer']              = $answer['text'] ?? ($answer['spokenText'] ?? '');
                                    $response['adaptive_cards']      = $answer['sourceAttributions'] ?? [];
                                    $response['suggested_responses'] = array_column($answer['suggestedResponses'] ?? [], 'text');

                                    BingConversations::where('id', $chat_id)->increment('invocation_id');

                                    $client->close();

                                    if (!$this->siri_use) {
                                        //                                        Log::info('发送消息：'.TelegramService::$key , $response);
                                        Redis::connection()->client()->set('last_message_answer:' . TelegramService::$key, json_encode($response), ['ex' => 3600]);
                                    }

                                    if ($return_array) {
                                        return ['code' => 1, 'message' => '', 'data' => $response];
                                    }

                                    return $this->success($response);
                                }
                            }
                        }

                        BingConversations::where('id', $chat_id)->increment('invocation_id');

                        $client->close();

                        if ($return_array) {
                            return ['code' => 1, 'message' => '', 'data' => $response];
                        }

                        return $this->success($response);
                    }

                    if (isset($message['type']) && 1 == $message['type']) {
                        // 检查是否有maxNumUserMessagesInConversation值
                        if (isset($message['arguments'][0]['throttling']['maxNumUserMessagesInConversation'])) {
                            $response['maxNumUserMessagesInConversation'] = $message['arguments'][0]['throttling']['maxNumUserMessagesInConversation'];
                            $response['numUserMessagesInConversation']    = $message['arguments'][0]['throttling']['numUserMessagesInConversation'];
                        }

                        $response['answer']         = $message['arguments'][0]['messages'][0]['text'] ?? '';

                        if (!isset($message['arguments'][0]['messages'][0]['messageType'])) {
                            $response['adaptive_cards'] = $message['arguments'][0]['messages'][0]['sourceAttributions'] ?? [];

                            $response['suggested_responses'] = array_column($message['arguments'][0]['messages'][0]['suggestedResponses'] ?? [], 'text');
                        }

                        //                        Log::info('正在返回答案：' . $response['answer'] ?: $response['adaptive_cards']);

                        if ($response['answer']) {
                            if (!$this->siri_use) {
                                //                                Log::info('发送消息：'.TelegramService::$key , $response);
                                Redis::connection()->client()->set('last_message_answer:' . TelegramService::$key, json_encode($response), ['ex' => 3600]);
                            }
                        }

                        continue;
                    }

                    if (isset($message['type']) && 7 == $message['type']) {
                        Log::info('需要重连', $message);

                        $client->close();

                        sleep(5);

                        ++$key;

                        if ($key <= $this->max_try) {
                            Log::info('需要重连');

                            // 删除原有的reids-key
                            Redis::connection()->client()->del('last_message_id:' . TelegramService::$key);

                            TelegramService::$last_message_id = 0;

                            return $this->connectWss('我没有收到，请重发一次', $chat_id, $return_array, $key);
                        }

                        if ($response['answer']) {
                            BingConversations::where('id', $chat_id)->increment('invocation_id');

                            if ($return_array) {
                                return ['code' => 1, 'message' => '', 'data' => $response];
                            }

                            return $this->success($response);
                        }
                    }

                    if (isset($message['type']) && 6 == $message['type']) {
                        // 心跳
                        $client->text(self::messageIdentifier(['type' => 6]));

                        $ping = time();
                    }

                    if (isset($message['type']) && 3 == $message['type']) {
                        // 结束
                        $client->close();

                        BingConversations::where('id', $chat_id)->increment('invocation_id');

                        if ($return_array) {
                            return ['code' => 1, 'message' => '', 'data' => $response];
                        }

                        return $this->success($response);
                    }
                }

                if ($response['answer']) {
                    if (!$this->siri_use) {
                        //                        Log::info('发送消息：'.TelegramService::$key , $response);
                        Redis::connection()->client()->set('last_message_answer:' . TelegramService::$key, json_encode($response), ['ex' => 3600]);
                    }
                }
                Log::info('等待中...' . $old);
            } catch (\WebSocket\ConnectionException $e) {
                Log::info($e->getMessage());

                sleep(2);

                ++$key;

                if ($key <= $this->max_try) {
                    Log::info('中途连接出错，重连：' . $e->getMessage());

                    return $this->connectWss($prompt, $chat_id, $return_array, $key);
                }

                if ($return_array) {
                    return ['code' => 0, 'message' => $e->getMessage()];
                }

                return $this->success($response);
                // Possibly log errors
            }
        }
    }

    private function handshark(Client $client, string $prompt, $chat_id)
    {
        $client->text(self::messageIdentifier(['protocol' => 'json', 'version' => 1]));

        $client->text(self::messageIdentifier(['type' => 6]));

        $client->text(self::messageIdentifier(self::updateWss($prompt, $chat_id)));
    }

    public function createConversation($return_array = false)
    {
        $url = 'https://edgeservices.bing.com/edgesvc/turing/conversation/create';

        try {
            $response = Http::withHeaders($this->getHeaders())->withoutVerifying()->acceptJson()->timeout(30)->get($url);

            $status_code = $response->status();

            if (200 != $status_code) {
                Log::error('gpt', ['code' => $status_code, 'msg' => $response->body()]);

                if ($return_array) {
                    return ['code' => 0, 'message' => 'Authentication failed,please replace the cookie'];
                }

                return $this->fail([$status_code, 'Authentication failed']);
            }

            $json = $response->json();

            if ('UnauthorizedRequest' == $json['result']['value']) {
                if ($return_array) {
                    return ['code' => 0, 'message' => 'Authentication failed,please replace the cookie'];
                }

                return $this->fail([$status_code, 'Authentication failed']);
            }

            unset($json['result']);

            $record = BingConversations::record(dataConvert($json));

            $json['chatId'] = $record->id;

            if ($return_array) {
                return ['code' => 1, 'message' => '', 'data' => $json];
            }

            return $this->success($json);
        } catch (ConnectionException $e) {
            if ($return_array) {
                return ['code' => 0, 'message' => 'connect timeout'];
            }

            return $this->fail([$e->getCode(), 'connect timeout']);
        } catch (RequestException $e) {
            if ($return_array) {
                return ['code' => 0, 'message' => $e->getMessage()];
            }

            return $this->fail([$e->getCode(), $e->getMessage()]);
        }
    }

    public function ask(string $question, $chat_id, $return_array = false, $siri_use = false)
    {
        $this->siri_use = $siri_use;
        Log::info('开始询问');

        $info = $this->connectWss($question, $chat_id, $return_array);

        if (isset($info['data']['numUserMessagesInConversation'])) {
            // 修改invocation_id
            BingConversations::where('id', $chat_id)->update(['invocation_id' => $info['data']['numUserMessagesInConversation']]);
        }

        if (isset($info['data']['adaptive_cards'])) {
            $arr = [];

            foreach ($info['data']['adaptive_cards'] as $key => $value) {
                $k = (int) $key + 1;

                $arr['inline_keyboard'][] = [
                    [
                        'text' => "[{$k}] " . $value['providerDisplayName'],
                        'url'  => $value['seeMoreUrl'],
                    ],
                ];
            }

            foreach ($info['data']['suggested_responses'] as $key => $value) {
                $k = (int) $key + 1;

                $arr['inline_keyboard'][] = [
                    [
                        'text'                             => "[推测{$k}] " . $value,
                        'switch_inline_query_current_chat' => $value,
                    ],
                ];
            }

            $info['data']['adaptive_cards'] = $arr;
        }

        if (!$this->siri_use) {
            Redis::connection()->client()->del('last_message_answer:' . TelegramService::$key);
        }

        return $info;
    }
}
