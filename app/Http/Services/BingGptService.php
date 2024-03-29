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

        return $json . "\x1e";
    }

    private static function updateWss($prompt, $chat_id)
    {
        $conversation = self::conversation($chat_id);

        return [
            'arguments' => [
                 [
                    'source'      => 'cib',
                    'optionsSets' => [
                        'nlu_direct_response_filter',
                        'deepleo',
                        'enable_debug_commands',
                        'disable_emoji_spoken_text',
                        'responsible_ai_policy_235',
                        'enablemm',
                    ],
                    'isStartOfSession' => 0 === self::$invocation_id,
                    'message'          => [
                        'author'      => 'user',
                        'inputMethod' => 'Keyboard',
                        'text'        => $prompt,
                        'messageType' => 'Chat',
                    ],
                    'conversationSignature' => $conversation->conversation_signature,
                    'participant'           => [
                        'id' => $conversation->client_id,
                    ],
                    'conversationId' => $conversation->conversation_id,
                ],
            ],
            'invocationId' => "0",
            'target'       => 'chat',
            'type'         => 4,
        ];
    }

    public function connectWss(string $prompt, $chat_id, $key = 0)
    {
        $ping = time();

        $context = stream_context_create();
        stream_context_set_option($context, 'ssl', 'verify_peer', false);
        stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

        $client = new Client('wss://sydney.bing.com/sydney/ChatHub', [
            'headers'      => config('bing.headers'),
            'timeout'      => 120,
            'fragment_size'=> 409600,
            'context'      => $context,
            //            'logger'       => Log::channel('daily'),
            //            'persistent'   => true,
        ]);

        $this->handshark($client,$prompt,$chat_id);

        $response = [
            'ask'           => '',
            'answer'        => '',
            'adaptive_cards'=> '',
        ];

        while (true) {
            try {
                if(!$client->isConnected()){
                    $this->handshark($client,$prompt,$chat_id);
                }

                $info = $client->receive();

                Log::info($info);

                $info = explode("\x1e", $info);

                $message = json_decode($info[0] ?? '', true);

                if ($message) {
                    if (isset($message['error'])) {
                        return $this->fail(ResponseEnum::CLIENT_NOT_FOUND_HTTP_ERROR, $message['error']);
                    }

                    if (isset($message['type']) && 2 == $message['type']) {
                        if (!isset($message['item']['messages'])) {
                            return $this->fail(ResponseEnum::CLIENT_NOT_FOUND_HTTP_ERROR, $message['item']['result']['message']);
                        }

                        foreach ($message['item']['messages'] as $answer) {
                            if (!isset($answer['messageType'])) {
                                if ('bot' == $answer['author']) {
                                    //答案已生成
                                    $response['answer']         = $answer['text'];
                                    $response['adaptive_cards'] = $answer['adaptiveCards'][0]['body'][0]['text'] ?? '';

                                    ++self::$invocation_id;

                                    BingConversations::where('id', $chat_id)->increment('invocation_id');

                                    return $this->success($response);
                                }
                                if ('user' == $answer['author']) {
                                    $response['ask'] = $answer['text'];
                                }
                            }
                        }

                        ++self::$invocation_id;

                        BingConversations::where('id', $chat_id)->increment('invocation_id');

                        return $this->success($response);
                    }
                }

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
    }

    private function handshark(Client $client,string $prompt, $chat_id)
    {
        $client->text(self::messageIdentifier(['protocol'=>'json', 'version'=>1]));

        $client->text(self::messageIdentifier(['type'=>6]));

        $client->text(self::messageIdentifier(self::updateWss($prompt, $chat_id)));
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
