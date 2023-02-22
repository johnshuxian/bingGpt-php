<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function ai(Request $request)
    {
        $service = TelegramService::getInstance();

        if ($request->all()) {
            $params = $request->all();

            if (!isset($params['message'])) {
                return 200;
            }

            return $service->telegram($params);
        }

        return 200;
    }
}
