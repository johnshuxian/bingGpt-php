<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\SiriService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SiriController extends Controller
{
    public function index(Request $request)
    {
        $siri_id = $request->input('uuid', '');

        $text = $request->input('text', '你好');

        $system = $request->input('system', '可靠的生活小助手，耐心，会非常详细的回答我的问题');

        if (!in_array($siri_id, config('telegram.siri'))) {
            return $this->fail([201, '未授权的账号']);
        }

        $token = $request->header('token');

        if (!$token) {
            return [
                'answer'=> '请在快捷方式中设置自己的token',
            ];
        }

        return [
            'answer'=> SiriService::getInstance()->siri($siri_id, $text, $token, $system),
        ];
    }
}