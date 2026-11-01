<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerificationIsSatisfied
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        if (! $request->user()?->canUseInteractiveFeatures()) {
            abort_if($request->expectsJson(), 403, 'Your email address is not verified.');

            return redirect()->guest(route($redirectToRoute ?? 'verification.notice'));
        }

        return $next($request);
    }
}
