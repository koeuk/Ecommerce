<?php

namespace App\Providers;

use App\Enums\Role;
use App\Payments\GatewayRegistry;
use App\Services\ImageService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ImageService wraps an Intervention manager that needs an explicit
        // driver, so it cannot be auto-resolved.
        $this->app->singleton(ImageService::class, fn () => ImageService::make());

        $this->app->singleton(
            GatewayRegistry::class,
            fn () => new GatewayRegistry(config('payments.gateways', [])),
        );
    }

    public function boot(): void
    {
        /*
         * The storefront owns the reset screen, so the emailed link points
         * there rather than at this app, which serves only the admin panel.
         */
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $frontend = rtrim((string) config('app.frontend_url'), '/');

            return $frontend.'/reset-password?token='.$token
                .'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });

        // Super admin bypasses every permission check.
        Gate::before(function ($user) {
            return $user->hasRole(Role::SuperAdmin->value) ? true : null;
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)
                ->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        // Checkout is expensive and writes stock, so it gets its own limit.
        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Storefront register/login. Keyed on email plus IP so one attacker
        // cannot lock a legitimate customer out by guessing their address.
        RateLimiter::for('customer-auth', function (Request $request) {
            return Limit::perMinute(10)
                ->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });
    }
}
