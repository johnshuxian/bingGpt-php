<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    //
    public function auth(Request $request): \Illuminate\Http\JsonResponse
    {
        $phone = $request->phone;

        $staff = Staff::where('phone',$phone)->first();

        $token = $staff->setEx(Carbon::now()->endOfDay())->createToken('staff')->plainTextToken;

        return $this->success(['token'=>$token]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if($user->tokens()->delete()){
            return $this->success([]);
        }

        return $this->fail();
    }
}
