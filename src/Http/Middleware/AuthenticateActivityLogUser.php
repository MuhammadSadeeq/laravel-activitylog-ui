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
        // Run whatever the application means by 'auth', every time — not only
        // when nobody is signed in.
        //
        // Treating a non-null user as proof of authentication skipped the host's
        // own middleware, and hosts put real decisions in there: the common
        // App\Http\Middleware\Authenticate subclass that rejects suspended or
        // unverified accounts stopped running, so a disabled user with a valid
        // session reached the audit log. Resolving the alias also keeps a host's
        // unauthenticated() override, which app(Authenticate::class) discards.
        //
        // Running it a second time after the host's own stack already did is
        // harmless: a successful guard check is idempotent, and Authenticate
        // calls shouldUse(), so the default guard is by then the one that
        // succeeded.
        $middleware = $this->hostAuthentication();

        if ($middleware === null) {
            return $next($request);
        }

        return $middleware->handle($request, $next, ...$this->guards());
    }

    /**
     * The application's own 'auth' middleware, or the framework's if it has not
     * aliased one.
     */
    protected function hostAuthentication(): ?object
    {
        $class = Authenticate::class;

        try {
            $aliases = app('router')->getMiddleware();
            $aliased = $aliases['auth'] ?? null;

            if (is_string($aliased) && class_exists($aliased)) {
                $class = $aliased;
            }
        } catch (\Throwable $e) {
            // No router, or no alias map: fall through to the framework class.
        }

        try {
            $middleware = app($class);
        } catch (\Throwable $e) {
            // An alias pointing at something unconstructable must not take the
            // page down; the access middleware behind this still refuses guests.
            return null;
        }

        return method_exists($middleware, 'handle') ? $middleware : null;
    }

    /**
     * Guards to authenticate against, from authorization.guard.
     *
     * Empty means the application's default guard, which is what 'auth' with no
     * parameters does. A token-only application whose default guard cannot read
     * its tokens needs to name one here.
     *
     * @return array<int, string>
     */
    protected function guards(): array
    {
        $guard = config('activitylog-ui.authorization.guard');

        return array_values(array_filter(is_array($guard) ? $guard : [$guard], 'is_string'));
    }
}
