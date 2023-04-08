<?php

namespace App\Http\Services;

use App\Models\TelegramChat;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Utils;

class GptService extends BaseService
{
    public function gpt3Stream($system, $chat_id, $prompt, $token='')
    {
        $token = $token ?: env('GPT3_TOKEN');

        $arr = [
            ['role' => 'system', 'content' => $system],
        ];

        if ($chat_id) {
            $records = TelegramChat::getRecords($chat_id, 'chat_id');

            foreach ($records as $record) {
                if ($record->is_bot) {
                    // 机器人
                    $arr[] =  ['role' => 'assistant', 'content' => $record->content];
                } else {
                    // 人类
                    $arr[] =  ['role' => 'user', 'content' => $record->content];
                }
            }
        }

        $arr[] = ['role' => 'user', 'content' => $prompt];

        $client = new Client([
            'timeout' => 300,
        ]);

        try {
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'json'    => [
                    'model'       => 'gpt-3.5-turbo',
                    'messages'    => $arr,
                    'temperature' => 1,
                    'stream'      => true,
                ],
                'stream'  => true,
            ]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                yield ['code' => 0, 'answer' => Message::toString($e->getResponse()), 'total_tokens' => 0];
            } else {
                yield ['code' => 0, 'answer' => $e->getMessage(), 'total_tokens' => 0];
            }
        }

        $data = $response->getBody();

        $answer = '';

        while (!$data->eof()) {
            $raw  = Utils::readLine($data);
            $line = self::formatStreamMessage($raw);

            if (self::checkFields($line)) {
                $answer = $line['choices'][0]['delta']['content'];

                yield ['code' => 1, 'answer' => $answer, 'total_tokens' => 0];
            }

            unset($raw, $line);
        }

    }

    public function gpt3($system, $chat_id, $prompt, $token='')
    {
        $token = $token ?: env('GPT3_TOKEN');

        $arr = [
            ['role' => 'system', 'content' => $system],
        ];

        if ($chat_id) {
            $records = TelegramChat::getRecords($chat_id, 'chat_id');

            foreach ($records as $record) {
                if ($record->is_bot) {
                    // 机器人
                    $arr[] =  ['role' => 'assistant', 'content' => $record->content];
                } else {
                    // 人类
                    $arr[] =  ['role' => 'user', 'content' => $record->content];
                }
            }
        }

        $arr[] = ['role' => 'user', 'content' => $prompt];

        $client = new Client([
            'timeout' => 300,
        ]);

        try {
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'json'    => [
                    'model'       => 'gpt-3.5-turbo',
                    'messages'    => $arr,
                    'temperature' => 1,
                ],
            ]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                return ['code' => 0, 'answer' => Message::toString($e->getResponse()), 'total_tokens' => 0];
            }

            return ['code' => 0, 'answer' => $e->getMessage(), 'total_tokens' => 0];

        }

        $data = $response->getBody();

        $data = json_decode($data->getContents(), true);

        // isset($data['error']['message']) ? 0 : 1, $data['error']['message'] ?? $data['choices'][0]['message']['content'], $data['usage']['total_tokens'] ?? 0
        return ['code' => isset($data['error']['message']) ? 0 : 1, 'answer' => $data['error']['message'] ?? $data['choices'][0]['message']['content'], 'total_tokens' => $data['usage']['total_tokens'] ?? 0];
    }

    public static function formatStreamMessage(string $line)
    {
        preg_match('/data: (.*)/', $line, $matches);
        if (empty($matches[1])) {
            return false;
        }

        $line = $matches[1];
        $data = json_decode($line, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return false;
        }

        return $data;
    }

    public static function checkFields($line): bool
    {
        return isset($line['choices'][0]['delta']['content']);
    }
}
