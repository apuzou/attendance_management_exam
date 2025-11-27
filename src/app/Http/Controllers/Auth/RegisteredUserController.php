<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Fortify;

class RegisteredUserController extends Controller
{
    protected $guard;

    public function __construct(StatefulGuard $guard)
    {
        $this->guard = $guard;
    }

    public function store(Request $request, CreatesNewUsers $creator)
    {
        if (config('fortify.lowercase_usernames')) {
            $request->merge([
                Fortify::username() => Str::lower($request->{Fortify::username()}),
            ]);
        }

        event(new Registered($user = $creator->create($request->all())));

        $this->guard->login($user);

        if ($user->role === 'general' && !$user->email_verified_at) {
            return redirect()->route('verification.notice');
        }

        if ($user->role === 'admin') {
            return redirect()->route('admin.index');
        }

        return redirect()->route('attendance.index');
    }
}

