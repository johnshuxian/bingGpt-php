<?php

namespace App\Http\Services;

use App\Exceptions\BusinessException;
use App\Helpers\ResponseEnum;
use App\Models\BingConversations;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use WebSocket\Client;

class BingGptService extends BaseService
{
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
        Log::info($json . (config('bing')['end'] ?? "\x1e"));

        return $json . (config('bing')['end'] ?? "\x1e");
    }

    private static function updateWss($prompt, $chat_id)
    {
        $conversation = self::conversation($chat_id);

        return [
            'arguments'   => [
                'source'     => 'cib',
                'optionsSets'=> [
                    'nlu_direct_response_filter',
                    'deepleo',
                    'enable_debug_commands',
                    'disable_emoji_spoken_text',
                    'responsible_ai_policy_235',
                    'enablemm',
                    'dv3sugg',
                ],
                'allowedMessageTypes'=> [
                    'Chat',
                    'InternalSearchQuery',
                    'InternalSearchResult',
                    'InternalLoaderMessage',
                    'RenderCardRequest',
                    'AdsQuery',
                    'SemanticSerp',
                ],
                'sliceIds'        => [],
                'isStartOfSession'=> 0 == self::$invocation_id,
                'message'         => [
                    'locale'       => 'zh-CN',
                    'market'       => 'zh-CN',
                    'region'       => 'US',
                    'location'     => 'lat:47.639557;long:-122.128159;re=1000m;',
                    'locationHints'=> [
                        [
                            'country'          => 'United States',
                            'state'            => 'California',
                            'city'             => 'Los Angeles',
                            'zipcode'          => '90017',
                            'timezoneoffset'   => -8,
                            'dma'              => 803,
                            'countryConfidence'=> 9,
                            'cityConfidence'   => 8,
                            'Center'           => [
                                'Latitude' => 34.0559,
                                'Longitude'=> -118.2705,
                            ],
                            'RegionType'=> 2,
                            'SourceType'=> 1,
                        ],
                    ],
                    'timestamp'  => Carbon::now()->toRfc3339String(),
                    'author'     => 'user',
                    'inputMethod'=> 'Keyboard',
                    'text'       => $prompt,
                    'messageType'=> 'chat',
                ],
                'conversationSignature'=> $conversation->conversation_signature,
                'participant'          => [
                    'id'=> $conversation->client_id,
                ],
                'conversationId'=> $conversation->conversation_id,
            ],
            'invocationId'=> (string) self::$invocation_id,
            'target'      => 'chat',
            'type'        => 4,
        ];
    }

    public function connectWss(string $prompt, $chat_id, $key = 0)
    {
        $ping = time();

        $context = stream_context_create();
        stream_context_set_option($context, 'ssl', 'verify_peer', false);
        stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

        $client = new Client('wss://sydney.bing.com/sydney/ChatHub', [
            'headers'      => config('bing.wss_headers'),
            'timeout'      => 120,
            'fragment_size'=> 40960,
            'context'      => $context,
            //            'logger'       => Log::channel('daily'),
            //            'persistent'   =>true,
        ]);

        while (!$client->isConnected()) {
            if (time() - $ping >= 5) {
                break;
            }
        }

        $client->text(self::messageIdentifier(['protocol'=>'json', 'version'=>1]));

        //ping
        $client->text(self::messageIdentifier(['type'=>6]));

        $client->text(self::messageIdentifier(self::updateWss($prompt, $chat_id)));

        $end = false;

        $response = [
            'user'          => '',
            'answer'        => '',
            'adaptive_cards'=> '',
        ];

        while (!$end) {
            try {
                $info = $client->receive();

                $message = json_decode(preg_replace('/\\x1e/', '', $info), true);

                Log::info($info);

                if ($message) {
                    /*                    if (isset($message['type']) && 7 == $message['type'] && ($message['allowReconnect'] ?? false) && $key <= 3) {
                    //                        return $this->connectWss($prompt, $chat_id, ++$key);
                                        }*/

                    if (isset($message['error'])) {
                        return $this->fail(ResponseEnum::CLIENT_NOT_FOUND_HTTP_ERROR, $message['error']);
                    }

                    if (isset($message['type']) && 2 == $message['type']) {
                        $response[] = $message['item']['messages'][1]['text'];

                        $end = true;

                        foreach ($message['item']['messages'] as $answer) {
                            if (!isset($answer['messageType'])) {
                                if ('bot' == $answer['author']) {
                                    //答案已生成
                                    $response['answer']         = $answer['text'];
                                    $response['adaptive_cards'] = $answer['adaptiveCards'][0]['body'][0]['text'] ?? '';

                                    return $this->success($response);
                                }
                                if ('user' == $answer['author']) {
                                    $response['user'] = $answer['text'];
                                }
                            }
                        }

                        ++self::$invocation_id;

                        BingConversations::where('id', $chat_id)->increment('invocation_id');

                        return $this->success($response);
                    }
                }
                $client->text(self::messageIdentifier(['type'=>6]));

                if (time() - $ping >= 30) {
                    $client->text(self::messageIdentifier(['type'=>6]));
                    $ping = time();
                }
            } catch (\WebSocket\ConnectionException $e) {
                Log::info($e->getMessage());

                return $this->success($response);
                // Possibly log errors
            }
        }

        return $this->success($response);
    }

    public function createConversation()
    {
        $url = 'https://edgeservices.bing.com/edgesvc/turing/conversation/create';

        try {
            $response = Http::withHeaders($this->getHeaders())->withoutVerifying()->acceptJson()->timeout(30)->get($url);

            $status_code = $response->status();

            if (200 != $status_code) {
                Log::error('gpt', ['code'=>$status_code, 'msg'=>$response->body()]);

                return $this->fail([$status_code, 'Authentication failed']);
            }

            $json = $response->json();

            if ('UnauthorizedRequest' == $json['result']['value']) {
                return $this->fail([$status_code, 'Authentication failed']);
            }

            unset($json['result']);

            $record = BingConversations::record(dataConvert($json));

            $json['chatId'] = $record->id;

            return $this->success($json);
        } catch (ConnectionException $e) {
            return $this->fail([$e->getCode(), 'connect timeout']);
        } catch (RequestException $e) {
            return $this->fail([$e->getCode(), $e->getMessage()]);
        }
    }

    public function ask(string $question, $chat_id)
    {
        return $this->connectWss($question, $chat_id);
    }
}
