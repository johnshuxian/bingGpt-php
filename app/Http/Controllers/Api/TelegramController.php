<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\BingGpt;
use App\Jobs\ChatGpt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    public function ai(Request $request)
    {
        if ($request->all()) {
            $params = $request->all();

            if (!isset($params['message'])) {
                return 200;
            }

            if(!in_array($params['message']['from']['id'],config('telegram.auth'))){
                return 200;
            }

            Log::info(json_encode($params));

            dispatch(new ChatGpt($params));

            return 200;
        }

        return 200;
    }

    public function bing(Request $request)
    {
        if ($request->all()) {
            $params = $request->all();

            if (!isset($params['message'])) {
                return 200;
            }

            if(!in_array($params['message']['from']['id'],config('telegram.auth'))){
                return 200;
            }

            Log::info(json_encode($params));

            dispatch(new BingGpt($params));

            return 200;
        }

        return 200;
    }
}
