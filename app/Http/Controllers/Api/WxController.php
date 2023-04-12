<?php

namespace App\Http\Controllers\Api;

use App\Http\Services\Other\WxService;
use App\Http\Services\SiriService;
use App\Jobs\NormalChatGpt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WxController
{
    public function index(Request $request, $bot)
    {
        $message = $request->all();

        if (!isset($message['encrypted'])) {
            return 'success';
        }

        $service = WxService::getInstance('0DFMKAC14myLewy', 'LQs5i0xoMOCZ1VU0HtnZ1fSmYYDkHG', 'FeRhS1BYFiyEcDpJoyNGCHII6T4KMBXGnlmpPQPECgP');

        $info = $service->decrypt($message['encrypted']);

        if (0 == $info[0]) {
            // 解析成功
            $msg = $info[1];

            Log::info('wx:', $msg);

            if (isset($msg['event']) && 'userQuit' == $msg['event'] && isset($msg['userid'])) {
                // 用户退出
                dispatch(new NormalChatGpt($msg['userid'], 'ok', 'wx-chatGpt', $msg['channel'], $service));

                return 'success';
            }

            if (!isset($msg['msgtype']) || !isset($msg['content']['query'])) {
                return 'success';
            }

            if ('text' != $msg['msgtype'] || empty($msg['content']['query'])) {
                return 'success';
            }

            $from = $msg['from'];

            if (1 != $from) {
                return 'success';
            }

            $prompt = $msg['content']['query'];

            switch ($bot) {
                case 'chatGpt':
                    $answer = SiriService::getInstance()->chatGpt($msg['userid'], $prompt, 'wx-chatGpt', false, $msg['userid']);

                    dispatch(new NormalChatGpt($msg['userid'], $prompt, 'wx-chatGpt', $msg['channel'], $service));

                    return 'success';

                    break;

                default:
                    $answer = '未知机器人';
            }

            $service->send($answer, $msg['userid'], $msg['channel']);
        }

        return 'success';
    }
}
