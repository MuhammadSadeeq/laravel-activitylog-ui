<?php

namespace MuhammadSadeeq\ActivitylogUi\Support;

use MuhammadSadeeq\ActivitylogUi\Http\Middleware\ActivityLogAccessMiddleware;
use MuhammadSadeeq\ActivitylogUi\Http\Middleware\AuthenticateActivityLogUser;

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
     * The package's own entries, and the alias it registers for one of them.
     */
    protected const OWN = [
        AuthenticateActivityLogUser::class,
        ActivityLogAccessMiddleware::class,
        'activitylog-access',
    ];

    /**
     * Append authentication and the access checks to a configured stack.
     *
     * Nothing here inspects the router. Aliases and middleware groups live on
     * the router instance, which is populated by the HTTP kernel and not by the
     * console kernel that `route:cache` boots through — so any decision made
     * from them would differ between the cached routes and the live ones.
     *
     * @param  array<int, mixed>  $middleware
     * @return array<int, mixed>
     */
    public static function protect(array $middleware): array
    {
        // Existing occurrences are dropped and re-appended, so both always run
        // last and in this order. A stack that listed the access middleware
        // before its own authentication — directly, or through the alias this
        // package registers — ran the allow-list checks against a guest, and
        // refused every request with a 401 that signing in could not clear.
        $middleware = array_values(array_filter(
            $middleware,
            fn ($entry) => ! static::isOwn($entry)
        ));

        $middleware[] = AuthenticateActivityLogUser::class;
        $middleware[] = ActivityLogAccessMiddleware::class;

        return $middleware;
    }

    /**
     * Whether an entry is one of this package's own.
     */
    protected static function isOwn(mixed $entry): bool
    {
        if (! is_string($entry)) {
            return false;
        }

        // Parameters are stripped so 'activitylog-access:something' matches too.
        return in_array(explode(':', $entry, 2)[0], static::OWN, true);
    }
}
