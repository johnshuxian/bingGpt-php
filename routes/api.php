<?php

use App\Http\Controllers\Api\BingGptController;
use App\Http\Controllers\Api\TelegramController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/**
 * 登录
 */

Route::namespace('Api')->group(function () {
    //创建会话
    Route::get('conversation/create', [BingGptController::class,'createConversation'])->name('conversation_create');

    Route::post('conversation/ask', [BingGptController::class,'ask'])->name('conversation_ask');

    Route::any('telegram/{method}', [TelegramController::class,'ai'])->name('telegram_ai');

    Route::post('siri', [\App\Http\Controllers\Api\SiriController::class,'index'])->name('siri_index');

    Route::post('gpt/ask', [\App\Http\Controllers\Api\SiriController::class,'ask'])->name('gpt_ask');

    Route::post('siri/bing', [\App\Http\Controllers\Api\SiriController::class,'bing'])->name('gpt_bing');

    Route::post('siri/gpt', [\App\Http\Controllers\Api\SiriController::class,'chatGpt'])->name('gpt_bing');

    Route::post('wx/{bot}', [\App\Http\Controllers\Api\WxController::class,'index'])->name('wx_index');

//    Route::any('telegram/bing', [TelegramController::class,'bing'])->name('telegram_bing');

});
