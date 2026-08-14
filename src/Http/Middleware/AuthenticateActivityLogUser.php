<?php

namespace MuhammadSadeeq\ActivitylogUi\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the request is authenticated, without assuming which guard.
 *
 * This used to be decided when routes were registered, by inspecting the
 * configured stack for something that looked like authentication and appending
 * a plain 'auth' when it found none. That inspection could not be made
 * reliable: middleware groups and aliases are registered on the router by the
 * HTTP kernel, and `route:cache` boots through the console kernel instead, so
 * the same configuration produced one stack at request time and a different one
 * baked into the cached routes.
 *
 * Asking at request time removes the guessing. Laravel's Authenticate calls
 * shouldUse() on success, so a host stack that authenticated under any guard —
 * 'auth:admin', a group containing it, an alias for its own subclass — leaves a
 * user on the request, and this passes straight through.
 */
class AuthenticateActivityLogUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        // Deferred to Laravel's own middleware so the application's configured
        // redirect and unauthenticated handling apply, rather than this package
        // guessing at a login route.
        return app(Authenticate::class)->handle($request, $next);
    }
}
