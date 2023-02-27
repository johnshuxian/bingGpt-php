<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\BingGpt;
use App\Jobs\ChatGpt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    public function ai(Request $request, $method)
    {
        if ($request->all()) {
            $params = $request->all();

            if (!isset($params['message'])) {
                return 200;
            }

            Log::info(json_encode($params));

            if (!in_array($params['message']['from']['id'], config('telegram.auth'))) {
                return 200;
            }

            switch ($method) {
                case 'ai'://chatGpt 机器人
                    dispatch(new ChatGpt($params));

                    break;

               case 'bing'://bingGpt机器人
                    dispatch(new BingGpt($params));

                    break;
            }

            return 200;
        }

        return 200;
    }
}
