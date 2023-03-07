<?php

namespace App\Http\Services;

use App\Models\TelegramChat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GptService extends BaseService
{
    public function gpt3($system, $chat_id, $prompt, $token='')
    {
        $token = $token ?: env('GPT3_TOKEN');

        Log::info('gpt请求中：'.$prompt);

        $records = TelegramChat::getRecords($chat_id, 'chat_id');

        $arr = [
            ['role'=>'system', 'content'=>$system],
        ];

        foreach ($records as $record) {
            if ($record->is_bot) {
                //机器人
                $arr[] =  ['role'=>'assistant', 'content'=>$record->content];
            } else {
                //人类
                $arr[] =  ['role'=>'user', 'content'=>$record->content];
            }
        }

        $arr[] = ['role'=>'user', 'content'=>$prompt];

        return Http::acceptJson()->withHeaders(
            ['Authorization' => 'Bearer ' . $token]
        )->timeout(300)->post('https://api.openai.com/v1/chat/completions', [
            'model'      => 'gpt-3.5-turbo',
            'messages'   => $arr,
            'temperature'=> 1,
            //                        'stop'  => [' Human:', ' AI:'],
        ])->json();
    }
}
