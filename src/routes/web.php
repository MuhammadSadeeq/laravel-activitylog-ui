<?php

use Illuminate\Support\Facades\Route;
use MuhammadSadeeq\ActivitylogUi\Http\Controllers\ActivityLogController;
use MuhammadSadeeq\ActivitylogUi\Http\Controllers\ExportController;

$config = config('activitylog-ui.route', []);
$prefix = $config['prefix'] ?? 'activitylog-ui';
$name = $config['name'] ?? 'activitylog-ui.';
$middleware = $config['middleware'] ?? ['web', 'auth'];
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

    // Debug route to check if package is working
    Route::get('/debug', function() {
        try {
            $activityCount = \MuhammadSadeeq\ActivitylogUi\Models\Activity::count();
            $recentActivities = \MuhammadSadeeq\ActivitylogUi\Models\Activity::latest()->limit(5)->get();

            return response()->json([
                'success' => true,
                'message' => 'ActivityLog UI is working!',
                'data' => [
                    'total_activities' => $activityCount,
                    'recent_activities' => $recentActivities,
                    'config' => [
                        'table_name' => config('activitylog.table_name', 'activity_log'),
                        'database_connection' => config('activitylog.database_connection'),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : 'Enable debug mode for full trace'
            ], 500);
        }
    })->name('debug');

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

        // Saved views endpoints
        Route::get('views', [ActivityLogController::class, 'getSavedViews'])
            ->name('views.index');

        Route::post('views', [ActivityLogController::class, 'saveView'])
            ->name('views.save');

        Route::delete('views', [ActivityLogController::class, 'deleteView'])
            ->name('views.delete');

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
