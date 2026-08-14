<?php

namespace MuhammadSadeeq\ActivitylogUi\Support;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Router;
use MuhammadSadeeq\ActivitylogUi\Http\Middleware\ActivityLogAccessMiddleware;

/**
 * Builds the route middleware stack for the UI.
 *
 * The host may replace the base stack entirely — that is the documented way to
 * swap 'web' for another group or add a tenancy layer — but authentication and
 * the access checks are appended on top and are not overridable.
 */
class RouteMiddleware
{
    /**
     * Append authentication and the access middleware to a configured stack.
     *
     * @param  array<int, mixed>  $middleware
     * @return array<int, mixed>
     */
    public static function protect(array $middleware): array
    {
        if (! static::authenticates($middleware)) {
            $middleware[] = 'auth';
        }

        // Any existing occurrence is dropped and the middleware re-appended, so it
        // always runs last. A stack that listed it before its own authentication
        // layer ran the access checks against a guest: allowed_users compares
        // against $request->user()->email, so every request was refused with a 401
        // that signing in could not fix.
        $middleware = array_values(array_filter(
            $middleware,
            fn ($entry) => $entry !== ActivityLogAccessMiddleware::class
        ));

        $middleware[] = ActivityLogAccessMiddleware::class;

        return $middleware;
    }

    /**
     * Whether a stack already authenticates the request.
     *
     * Matching only the literal string 'auth' meant a stack using a named guard
     * ('auth:admin'), the class name, or a group containing either got a second,
     * default-guard 'auth' appended. On an app whose users live behind a
     * non-default guard that entry always fails, so the UI became unreachable for
     * exactly the people configured to reach it.
     *
     * @param  array<int, mixed>  $middleware
     */
    public static function authenticates(array $middleware, int $depth = 0): bool
    {
        foreach ($middleware as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            if (static::isAuthentication($entry)) {
                return true;
            }

            // Groups nest, and 'web' is itself a group; three levels is well past
            // anything real and stops a self-referential group from looping.
            if ($depth < 3 && ($group = static::group($entry)) !== null) {
                if (static::authenticates($group, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a single middleware entry authenticates.
     */
    protected static function isAuthentication(string $entry): bool
    {
        // 'auth:admin' and 'auth:sanctum,web' are the same middleware with
        // parameters; only the part before the colon names it.
        $name = explode(':', $entry, 2)[0];

        if (in_array($name, ['auth', 'auth.basic', 'auth.session'], true)) {
            return true;
        }

        // An alias may point at any class, including the application's own
        // Authenticate subclass.
        $resolved = static::alias($name) ?? $name;

        return is_string($resolved)
            && class_exists($resolved)
            && is_a($resolved, Authenticate::class, true);
    }

    protected static function alias(string $name): ?string
    {
        $aliases = static::router()?->getMiddleware() ?? [];

        return is_string($aliases[$name] ?? null) ? $aliases[$name] : null;
    }

    /**
     * @return array<int, mixed>|null
     */
    protected static function group(string $name): ?array
    {
        $groups = static::router()?->getMiddlewareGroups() ?? [];

        return is_array($groups[$name] ?? null) ? $groups[$name] : null;
    }

    protected static function router(): ?Router
    {
        // Resolved lazily and defensively: this runs while routes are being
        // registered, and a container without a bound router must degrade to
        // literal matching rather than fail route registration outright.
        try {
            $router = app('router');
        } catch (\Throwable) {
            return null;
        }

        return $router instanceof Router ? $router : null;
    }
}
