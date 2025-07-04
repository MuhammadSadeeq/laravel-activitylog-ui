<?php

namespace MuhammadSadeeq\ActivitylogUi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If authorization is completely disabled, allow access
        if (!config('activitylog-ui.authorization.enabled', false)) {
            // Still check access controls if they are defined
            $allowedUsers = config('activitylog-ui.access.allowed_users', []);
            $allowedRoles = config('activitylog-ui.access.allowed_roles', []);

            // If access controls are defined, require authentication
            if (!empty($allowedUsers) || !empty($allowedRoles)) {
                if (!$request->user()) {
                    abort(401, 'Authentication required for Activity Log UI.');
                }

                // Check allowed users
                if (!empty($allowedUsers) && !in_array($request->user()->email, $allowedUsers)) {
                    abort(403, 'User not allowed to access Activity Log UI.');
                }

                // Check allowed roles
                if (!empty($allowedRoles) && !$request->user()->hasAnyRole($allowedRoles)) {
                    abort(403, 'User role not allowed to access Activity Log UI.');
                }
            }

            return $next($request);
        }

        // Authorization is enabled - check gate authorization
        if (Gate::denies('viewActivityLogUi')) {
            abort(403, 'Unauthorized access to Activity Log UI.');
        }

        // Check allowed users
        $allowedUsers = config('activitylog-ui.access.allowed_users', []);
        if (!empty($allowedUsers) && !in_array($request->user()?->email, $allowedUsers)) {
            abort(403, 'User not allowed to access Activity Log UI.');
        }

        // Check allowed roles
        $allowedRoles = config('activitylog-ui.access.allowed_roles', []);
        if (!empty($allowedRoles) && !$request->user()?->hasAnyRole($allowedRoles)) {
            abort(403, 'User role not allowed to access Activity Log UI.');
        }

        return $next($request);
    }
}
