<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ChatGpt;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function ai(Request $request)
    {
        if ($request->all()) {
            $params = $request->all();

            if (!isset($params['message'])) {
                return 200;
            }

            dispatch(new ChatGpt($params));

            return 200;
        }

        return 200;
    }
}
