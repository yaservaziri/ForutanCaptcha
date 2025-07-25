<?php

use Illuminate\Support\Facades\Route;
use Forutan\Captcha\Http\Controllers\FCaptchaController;

Route::group([
    'prefix' => config('fcaptcha.route_prefix', 'fcaptcha'),
    'middleware' => config('fcaptcha.middleware', ['web']),
    'as' => 'fcaptcha.',
], function () {
    Route::get('/', [FCaptchaController::class, 'show'])->name('show')->middleware(['fcaptcha.redirect_if_passed', 'throttle:' . config('fcaptcha.throttle.show', '100,1')]);
    Route::post('/verify', [FCaptchaController::class, 'verify'])->name('verify')->middleware('throttle:' . config('fcaptcha.throttle.verify', '100,1'));;
    Route::get('/image/{hash}', [FCaptchaController::class, 'image'])->name('image')->middleware('throttle:' . config('fcaptcha.throttle.image', '100,1'));;
});
