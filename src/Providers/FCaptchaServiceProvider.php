<?php

namespace Forutan\Captcha\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Forutan\Captcha\Commands\FcaptchaPrepareImages;
use Forutan\Captcha\Http\Middleware\EnsureFCaptchaIsVerified;
use Forutan\Captcha\Http\Middleware\RedirectIfFCaptchaAlreadyPassed;

class FCaptchaServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'fcaptcha');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->publishes([
            __DIR__ . '/../Config/fcaptcha.php' => config_path('fcaptcha.php'),
        ], 'fcaptcha-config');
        $this->publishes([
            __DIR__ . '/../../resources/views' => resource_path('views/vendor/fcaptcha'),
        ], 'fcaptcha-views');

        $this->publishes([
            __DIR__ . '/../../database/fcaptcha_seeder' => storage_path('app/private/fcaptcha_seeder'),
        ], 'fcaptcha-seeds');

        if ($this->app->runningInConsole()) {
            $this->commands([
                FcaptchaPrepareImages::class,
            ]);
        }

        $this->app->make(Router::class)->aliasMiddleware(
            'fcaptcha.verified', EnsureFCaptchaIsVerified::class
        );
        $this->app->make(Router::class)->aliasMiddleware(
            'fcaptcha.redirect_if_passed', RedirectIfFCaptchaAlreadyPassed::class
        );
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/fcaptcha.php',
            'fcaptcha'
        );
    }
}
