<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Services\AuthenticationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(
            \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class,
            \App\Http\Controllers\Auth\AuthenticatedSessionController::class
        );
    }

    public function boot()
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;
            return Limit::perMinute(5)->by($email.$request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        Fortify::loginView(function () {
            return view('auth.login');
        });

        Fortify::registerView(function () {
            return view('auth.register');
        });

        Fortify::authenticateUsing(function (Request $request) {
            $user = AuthenticationService::authenticate($request->email, $request->password);

            if ($user && $request->has('is_admin_login') && $request->is_admin_login == '1') {
                if ($user->role !== 'admin') {
                    return null;
                }
            }

            return $user;
        });

        $this->app->singleton(RegisterResponse::class, function () {
            return new class implements RegisterResponse {
                public function toResponse($request)
                {
                    return redirect()->route('verification.notice');
                }
            };
        });

        $this->app->singleton(LoginResponse::class, function () {
            return new class implements LoginResponse {
                public function toResponse($request)
                {
                    /** @var \App\Models\User $user */
                    $user = Auth::user();
                    $user->refresh();

                    if ($request->has('is_admin_login') && $request->is_admin_login == '1') {
                        session(['is_admin_login' => true]);
                        return redirect()->route('admin.index');
                    }

                    session()->forget('is_admin_login');

                    if ($user->email_verified_at === null) {
                        return redirect()->route('verification.notice');
                    }

                    return redirect()->route('attendance.index');
                }
            };
        });

        $this->app->singleton(LogoutResponse::class, function () {
            return new class implements LogoutResponse {
                public function toResponse($request)
                {
                    $isAdminLogin = session('is_admin_login', false);
                    $referer = $request->headers->get('referer');
                    $isAdminPage = $referer && Str::contains($referer, '/admin/');

                    if ($isAdminLogin || $isAdminPage) {
                        return redirect()->route('admin.login');
                    }

                    return redirect()->route('login');
                }
            };
        });
    }
}

