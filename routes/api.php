<?php

use App\Http\Controllers\Api\AuthController;
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
    //登录
    Route::post('/auth', [AuthController::class,'auth'])->name('auth');

    Route::middleware('checkToken')->group(function () {
        //退出登录
        Route::get('/logout', [AuthController::class, 'logout']);
    });
});
