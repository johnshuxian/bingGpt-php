<?php

use App\Http\Controllers\Api\BingGptController;
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
});
