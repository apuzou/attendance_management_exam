<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            if ($user->role === 'general' && !$user->email_verified_at) {
                if (!$request->routeIs('verification.*')) {
                    return redirect()->route('verification.notice');
                }
            }
        }

        return $next($request);
    }
}

