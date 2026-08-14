<?php

use Illuminate\Support\Facades\Route;
use MuhammadSadeeq\ActivitylogUi\Http\Controllers\ActivityLogController;
use MuhammadSadeeq\ActivitylogUi\Http\Controllers\ExportController;

$config = config('activitylog-ui.route', []);
$prefix = $config['prefix'] ?? 'activitylog-ui';
$name = $config['name'] ?? 'activitylog-ui.';

// A custom stack replaces the base middleware, so someone can swap 'web' for
// their own group or add a tenancy layer.
$middleware = $config['middleware'] ?? ['web'];

// Authorization is appended afterwards and is NOT overridable. Letting a custom
// stack replace it meant an app that set route.middleware lost authentication
// and the access lists entirely, and served the whole audit log publicly.
// The fallback is true so a missing or partial config fails closed; the
// controllers already assumed true here while this file assumed false.
$accessMiddleware = \MuhammadSadeeq\ActivitylogUi\Http\Middleware\ActivityLogAccessMiddleware::class;

if (config('activitylog-ui.authorization.enabled', true)) {
    if (!in_array('auth', $middleware, true)) {
        $middleware[] = 'auth';
    }

    if (!in_array($accessMiddleware, $middleware, true)) {
        $middleware[] = $accessMiddleware;
    }
} elseif (config('activitylog-ui.access.allowed_users') || config('activitylog-ui.access.allowed_roles')) {
    // The middleware enforces these lists even with authorization disabled, but
    // it was only ever registered when authorization was enabled — so anyone who
    // set them while leaving authorization off got no protection at all.
    //
    // 'auth' comes too: an allow-list requires a logged-in user either way, and
    // without it a guest got a bare 401 with no route to signing in — so the
    // frontend's session-expiry reload landed on an error page rather than a
    // login form.
    if (!in_array('auth', $middleware, true)) {
        $middleware[] = 'auth';
    }

    if (!in_array($accessMiddleware, $middleware, true)) {
        $middleware[] = $accessMiddleware;
    }
}

$domain = $config['domain'] ?? null;

Route::group([
    'prefix' => $prefix,
    'as' => $name,
    'middleware' => $middleware,
    'domain' => $domain,
], function () {

    // Main dashboard route
    Route::get('/', [ActivityLogController::class, 'index'])
        ->name('dashboard');

    // Activity log data endpoints
    Route::prefix('api')->as('api.')->group(function () {

        // Activities endpoints
        Route::get('activities', [ActivityLogController::class, 'getActivities'])
            ->name('activities.index');

        Route::get('activities/{id}', [ActivityLogController::class, 'getActivity'])
            ->name('activities.show');

        Route::get('activities/{id}/related', [ActivityLogController::class, 'getActivityRelated'])
            ->name('activities.related');

        Route::get('search/suggestions', [ActivityLogController::class, 'getSearchSuggestions'])
            ->name('search.suggestions');

        Route::get('filter-options', [ActivityLogController::class, 'getFilterOptions'])
            ->name('filter.options');

        Route::get('event-types-styling', [ActivityLogController::class, 'getEventTypesWithStyling'])
            ->name('event-types.styling');

        Route::get('recent', [ActivityLogController::class, 'recent'])
            ->name('activities.recent');

        // Analytics endpoints
        Route::get('analytics', [ActivityLogController::class, 'analytics'])
            ->name('analytics');

        Route::get('analytics/heatmap', [ActivityLogController::class, 'heatmap'])
            ->name('analytics.heatmap');

        Route::get('users/{userId}/profile', [ActivityLogController::class, 'userProfile'])
            ->name('users.profile');

        // Saved views endpoints (only if feature is enabled)
        if (config('activitylog-ui.features.saved_views', true)) {
            Route::get('views', [ActivityLogController::class, 'getSavedViews'])
                ->name('views.index');

            Route::post('views', [ActivityLogController::class, 'saveView'])
                ->name('views.save');

            Route::delete('views', [ActivityLogController::class, 'deleteView'])
                ->name('views.delete');
        }

        // Export endpoints
        Route::post('export', [ExportController::class, 'export'])
            ->name('export');

        Route::get('export/formats', [ExportController::class, 'formats'])
            ->name('export.formats');

        Route::get('export/progress', [ExportController::class, 'progress'])
            ->name('export.progress');

        Route::post('export/cleanup', [ExportController::class, 'cleanup'])
            ->name('export.cleanup');
    });

    // Export download route (outside API group for direct file serving)
    Route::get('export/download', [ExportController::class, 'download'])
        ->name('export.download');
});
