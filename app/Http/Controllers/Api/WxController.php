<?php

namespace App\Http\Controllers\Api;

use App\Http\Services\Other\WxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WxController
{
    public function index(Request $request)
    {
        $message = $request->all();

        Log::info('wx:', $message);

        $message['xml'] = file_get_contents('php://input');

        $service = new WxService('0DFMKAC14myLewy','LQs5i0xoMOCZ1VU0HtnZ1fSmYYDkHG','FeRhS1BYFiyEcDpJoyNGCHII6T4KMBXGnlmpPQPECgP');

        $info = $service->decryptMsg($message);

        if (0 == $info[0]) {
            // 解析成功
        }

        return 'success';
    }
}
