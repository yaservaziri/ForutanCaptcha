<?php

namespace Forutan\Captcha\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectIfFCaptchaAlreadyPassed
{
    public function handle(Request $request, Closure $next)
    {
        $context = session('fcaptcha_context', 'default');
    
        if (session("fcaptcha_passed.$context", false)) {
            return redirect(config("fcaptcha.redirect_on_pass.$context", '/'));
        }
    
        return $next($request);
    }
}
