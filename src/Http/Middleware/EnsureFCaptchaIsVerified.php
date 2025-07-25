<?php

namespace Forutan\Captcha\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureFCaptchaIsVerified
{
    public function handle(Request $request, Closure $next, $context = 'default')
    {
        if (!session("fcaptcha_passed.$context", false)) {
            session(['fcaptcha_context' => $context,]);
            return redirect()->route('fcaptcha.show')
                ->with('error', 'Please verify you are human to continue.');
        }
        return $next($request);
    }
}
