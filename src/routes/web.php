<?php

use Illuminate\Support\Facades\Route;
use MuhammadSadeeq\ActivitylogUi\Http\Controllers\ActivityLogController;
use MuhammadSadeeq\ActivitylogUi\Http\Controllers\ExportController;

$config = config('activitylog-ui.route', []);
$prefix = $config['prefix'] ?? 'activitylog-ui';
$name = $config['name'] ?? 'activitylog-ui.';

// Build middleware based on authorization configuration
$middleware = ['web'];
if (config('activitylog-ui.authorization.enabled', false)) {
    $middleware[] = 'auth';
    $middleware[] = \MuhammadSadeeq\ActivitylogUi\Http\Middleware\ActivityLogAccessMiddleware::class;
}

// Allow custom middleware override if provided
if (isset($config['middleware'])) {
    $middleware = $config['middleware'];
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
