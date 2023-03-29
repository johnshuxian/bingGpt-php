<?php

namespace App\Http\Services;

use App\Exceptions\BusinessException;
use App\Helpers\ResponseEnum;
use App\Models\BingConversations;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use WebSocket\Client;

class BingGptService extends BaseService
{
    /**
     * @var false|mixed
     */
    private mixed $siri_use;

    public function __construct()
    {
        parent::__construct();

        set_time_limit(0);
    }

    protected static $invocation_id;

    protected static $conversation = [];

    private static function conversation($chat_id)
    {
        if (isset(self::$conversation[$chat_id])) {
            return self::$conversation[$chat_id];
        }

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

        $replace = ['tone', 'format', 'length'];

        $prompt = $bing['prompt'];

        $prompt = str_replace('%text', $question, $prompt);

        foreach ($replace as $item) {
            $prompt = str_replace('%' . $item, $bing[$item], $prompt);
        }

        $conversation = self::conversation($chat_id);

        $info = [
            'arguments'    => [
                [
                    'source'                => 'cib',
                    'optionsSets'           => [
                        'nlu_direct_response_filter',
                        'deepleo',
                        'enable_debug_commands',
                        'disable_emoji_spoken_text',
                        'responsible_ai_policy_235',
                        'enablemm',
                        'nocache',
                        'nosugg',
                        'gencontentv3',
                        'cachewriteext',
                        'contentability',
                        'e2ecachewrite',
                        'hubcancel',
                        'telmet',
                        'dl_edge_prompt',
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
                        //                        'anidtestcf',
                        //                        '321bic62up',
                        //                        '321bic62',
                        //                        'creatorv2t',
                        //                        'sydpayajaxlog',
                        //                        'perfsvgopt',
                        //                        'toneexpcf',
                        //                        '323trffov',
                        //                        '323frep',
                        //                        '303hubcancls0',
                        //                        '320newspole',
                        //                        '321throt',
                        //                        '321slocs0',
                        //                        '316e2ecache',
                    ],
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
            'invocationId' => '0',
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
        $ping = $start = time();

        $context = stream_context_create();
        stream_context_set_option($context, 'ssl', 'verify_peer', false);
        stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

        try {
            $client = new Client('wss://sydney.bing.com/sydney/ChatHub', [
                'headers'       => config('bing.headers'),
                'timeout'       => 120,
                'fragment_size' => 409600,
                'context'       => $context,
                //            'logger'       => Log::channel('daily'),
                //            'persistent'   => true,
            ]);

            $this->handshark($client, $prompt, $chat_id);
        } catch (\Exception $exception) {
            // 连接错误，重试

            ++$key;

            if ($key <= 3) {
                return $this->connectWss($prompt, $chat_id, $return_array, $key);
            }

            if ($return_array) {
                return ['code' => 0, 'message' => $exception->getMessage()];
            }

            return $this->fail(ResponseEnum::CLIENT_NOT_FOUND_HTTP_ERROR, $exception->getMessage());
        }

        $response = [
            'ask'                              => '',
            'answer'                           => '',
            'adaptive_cards'                   => '',
            'maxNumUserMessagesInConversation' => 0,
            'numUserMessagesInConversation'    => 0,
        ];

        $index = 0;

        while (true) {
            try {
                if (!$client->isConnected()) {
                    if (!$response['answer']) {
                        $this->handshark($client, $prompt, $chat_id);

                        continue;
                    }

                    BingConversations::where('id', $chat_id)->increment('invocation_id');

                    if ($return_array) {
                        return ['code' => 1, 'message' => '', 'data' => $response];
                    }

                    return $this->success($response);
                }

                $info = $client->receive();

                $info = explode("\x1e", $info);

                $message = json_decode($info[0] ?? '', true);

                Log::info(json_encode($message));

                if ($message) {
                    if (isset($message['error'])) {
                        if ($return_array) {
                            return ['code' => 0, 'message' => $message['error']];
                        }

                        return $this->fail(ResponseEnum::CLIENT_NOT_FOUND_HTTP_ERROR, $message['error']);
                    }

                    if (isset($message['type']) && 2 == $message['type']) {
                        if (!isset($message['item']['messages'])) {
                            if ($return_array) {
                                return ['code' => 0, 'message' => $message['item']['result']['message']];
                            }

                            return $this->fail(ResponseEnum::CLIENT_NOT_FOUND_HTTP_ERROR, $message['item']['result']['message']);
                        }

                        $response['maxNumUserMessagesInConversation'] = $message['item']['throttling']['maxNumUserMessagesInConversation'] ?? 0;
                        $response['numUserMessagesInConversation']    = $message['item']['throttling']['numUserMessagesInConversation'] ?? 0;

                        foreach ($message['item']['messages'] as $answer) {
                            if (!isset($answer['messageType'])) {
                                if ('bot' == $answer['author']) {
                                    // 答案已生成
                                    $response['answer']         = $answer['text'];
                                    $response['adaptive_cards'] = $answer['adaptiveCards'][0]['body'][0]['text'] ?? '';

                                    BingConversations::where('id', $chat_id)->increment('invocation_id');

                                    if ($return_array) {
                                        return ['code' => 1, 'message' => '', 'data' => $response];
                                    }

                                    return $this->success($response);
                                }
                                if ('user' == $answer['author']) {
                                    $response['ask'] = $answer['text'];
                                }
                            }
                        }

                        BingConversations::where('id', $chat_id)->increment('invocation_id');

                        if ($return_array) {
                            return ['code' => 1, 'message' => '', 'data' => $response];
                        }

                        return $this->success($response);
                    }

                    if (isset($message['type']) && 1 == $message['type']) {
                        ++$index;
                        $response['ask']            = $prompt;
                        $response['answer']         = $message['arguments'][0]['messages'][0]['text'] ?? 'bing超时未正常返回答案';
                        $response['adaptive_cards'] = $message['arguments'][0]['messages'][0]['adaptiveCards'][0]['body'][0]['text'] ?? '';

                        $last = $message['arguments'][count($message['arguments']) - 1]['messages'][0]['text'] ?? '';

                        if (1 == $index && !$this->siri_use) {
                            TelegramService::sendOrUpdate('稍等，回答正在生成中...');
                        }
                    }

                    if (isset($message['type']) && 7 == $message['type']) {
                        ++$key;

                        if ($key <= 3) {
                            return $this->connectWss($prompt, $chat_id, $return_array, $key);
                        }

                        if ($response['answer']) {
                            BingConversations::where('id', $chat_id)->increment('invocation_id');

                            if ($return_array) {
                                return ['code' => 1, 'message' => '', 'data' => $response];
                            }

                            return $this->success($response);
                        }
                    }
                }

                if (time() - $ping >= 30) {
                    $client->text(self::messageIdentifier(['type' => 6]));
                    $ping = time();
                }

                if (time() - $start >= 360) {
                    // 超时仍未返回，返回最近的type 1的内容

                    BingConversations::where('id', $chat_id)->increment('invocation_id');

                    if ($return_array) {
                        return ['code' => 1, 'message' => '', 'data' => $response];
                    }

                    return $this->success($response);
                }
            } catch (\WebSocket\ConnectionException $e) {
                Log::info($e->getMessage());

                ++$key;

                if ($key <= 3) {
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

        return $this->connectWss($question, $chat_id, $return_array);
    }
}
