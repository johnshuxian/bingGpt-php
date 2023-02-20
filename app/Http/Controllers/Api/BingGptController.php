<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseEnum;
use App\Http\Controllers\Controller;
use App\Http\Services\BingGptService;
use Illuminate\Http\Request;

class BingGptController extends Controller
{
    public function createConversation()
    {
        $gptService = BingGptService::getInstance();

        return $gptService->createConversation();
    }

    public function ask(Request $request){
        $question = $request->input('prompt','');
        $chat_id  = $request->input('chatId',0);

        if(!$question){
            return $this->fail(ResponseEnum::CLIENT_PARAMETER_ERROR);
        }

        $gptService = BingGptService::getInstance();

        return $gptService->ask($question,$chat_id);
    }
}
