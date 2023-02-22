<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class TelegramController extends Controller
{
    public function ai(Request $request)
    {
        $service = TelegramService::getInstance();

        if ($request->all()) {
            while (!Redis::connection()->client()->set('bot:lock', 1, ['nx', 'ex'=>120])) {
                sleep(0.1);
            }

            $params = $request->all();

            if (!isset($params['message'])) {
                return 200;
            }

            $info = $service->telegram($params);

            Redis::connection()->client()->del('bot:lock');

            return $info;
        }

        return 200;
    }
}
