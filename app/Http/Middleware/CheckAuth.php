<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessException;
use App\Helpers\ResponseEnum;
use Closure;
use Illuminate\Http\Request;

class CheckAuth
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            throw new BusinessException(ResponseEnum::CLIENT_NOT_AUTH);
        }

        return $next($request);
    }
}
