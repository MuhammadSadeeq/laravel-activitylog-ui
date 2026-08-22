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
        if (!config('activitylog-ui.authorization.enabled', true)) {
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
                if (!empty($allowedRoles) && !$this->hasAnyAllowedRole($request->user(), $allowedRoles)) {
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
        if (!empty($allowedRoles) && !$this->hasAnyAllowedRole($request->user(), $allowedRoles)) {
            abort(403, 'User role not allowed to access Activity Log UI.');
        }

        return $next($request);
    }

    /**
     * Whether the user holds one of the allowed roles.
     *
     * Roles come from whatever package the host uses, so the method may not
     * exist at all. Calling it blindly turned "you configured allowed_roles
     * without a role package" into a 500; a user who cannot be shown to hold an
     * allowed role is simply denied.
     *
     * @param  array<int, string>  $allowedRoles
     */
    protected function hasAnyAllowedRole(mixed $user, array $allowedRoles): bool
    {
        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return (bool) $user->hasAnyRole($allowedRoles);
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($allowedRoles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        return false;
    }
}
