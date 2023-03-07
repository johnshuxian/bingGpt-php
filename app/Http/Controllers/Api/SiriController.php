<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\SiriService;
use Illuminate\Http\Request;

class SiriController extends Controller
{
    public function index(Request $request)
    {
        $siri_id = $request->input('uuid', '');

        $text = $request->input('text');

        if (!in_array($siri_id, config('telegram.siri'))) {
            return $this->fail([201, '未授权的账号']);
        }

        return [
            'answer'=> SiriService::getInstance()->siri($siri_id, $text),
        ];
    }
}
