<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use App\Policies\AttendancePolicy;
use App\Policies\StampCorrectionRequestPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * 認可サービスプロバイダ
 *
 * ポリシーマッピングと認可サービスの登録を行う。
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Attendance::class => AttendancePolicy::class,
        StampCorrectionRequest::class => StampCorrectionRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
