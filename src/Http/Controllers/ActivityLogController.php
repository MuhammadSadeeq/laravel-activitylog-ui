<?php

namespace MuhammadSadeeq\ActivitylogUi\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Routing\Controller;
use MuhammadSadeeq\ActivitylogUi\Services\ActivitylogService;
use MuhammadSadeeq\ActivitylogUi\Services\AnalyticsService;

class ActivityLogController extends Controller
{
    protected ActivitylogService $activitylogService;
    protected AnalyticsService $analyticsService;

    public function __construct(
        ActivitylogService $activitylogService,
        AnalyticsService $analyticsService
    ) {
        $this->activitylogService = $activitylogService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Display the main activity log dashboard.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewActivityLogUi');

        $filters = $this->getFiltersFromRequest($request);
        $view = $request->get('view', config('activitylog-ui.ui.default_view', 'table'));
        $perPage = $request->get('per_page', config('activitylog-ui.ui.default_per_page', 25));

        // Get activities based on view type
        if ($view === 'timeline') {
            $data = $this->activitylogService->getTimelineActivities($filters, $perPage);
        } else {
            $data = $this->activitylogService->getActivities($filters, $perPage);
        }

        // Get filter options
        $filterOptions = [
            'causers' => $this->activitylogService->getAvailableCausers(),
            'subject_types' => $this->activitylogService->getAvailableSubjectTypes(),
            'event_types' => $this->activitylogService->getAvailableEventTypes(),
            'date_presets' => config('activitylog-ui.filters.date_presets', []),
        ];

        // Get saved views (only if feature is enabled)
        $savedViews = config('activitylog-ui.features.saved_views', true)
            ? $this->activitylogService->getSavedViews($request->user()?->id)
            : [];

        return view('activitylog-ui::pages.dashboard', compact(
            'data',
            'filters',
            'view',
            'filterOptions',
            'savedViews',
            'perPage'
        ));
    }

    /**
     * Get activities data for AJAX requests.
     */
    public function getData(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        $filters = $this->getFiltersFromRequest($request);
        $view = $request->get('view', config('activitylog-ui.ui.default_view', 'table'));
        $perPage = $request->get('per_page', config('activitylog-ui.ui.default_per_page', 25));

        if ($view === 'timeline') {
            $data = $this->activitylogService->getTimelineActivities($filters, $perPage);
        } else {
            $data = $this->activitylogService->getActivities($filters, $perPage);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get activity detail.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        $activity = $this->activitylogService->getActivityDetail($id);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Activity not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'activity' => $activity,
                'formatted_changes' => $activity->formatted_changes,
                'has_changes' => $activity->hasPropertyChanges(),
            ],
        ]);
    }

    /**
     * Search activities with suggestions.
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        $request->validate([
            'query' => 'required|string|min:2|max:100',
        ]);

        $query = $request->get('query');
        $results = $this->activitylogService->searchWithSuggestions($query);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Save a custom view.
     */
    public function saveView(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        if (!config('activitylog-ui.features.saved_views', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Saved views feature is disabled.',
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'filters' => 'required|array',
        ]);

        $view = $this->activitylogService->saveView(
            $request->get('filters'),
            $request->get('name'),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'View saved successfully.',
            'data' => $view,
        ]);
    }

    /**
     * Delete a saved view.
     */
    public function deleteView(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        if (!config('activitylog-ui.features.saved_views', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Saved views feature is disabled.',
            ], 403);
        }

        $request->validate([
            'view_id' => 'required|string',
        ]);

        $this->activitylogService->deleteSavedView(
            $request->get('view_id'),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'View deleted successfully.',
        ]);
    }

    /**
     * Get analytics dashboard data.
     */
    public function analytics(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        if (!config('activitylog-ui.features.analytics', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Analytics feature is disabled.',
            ], 403);
        }

        // Get period or custom date range
        $filters = [];

        if ($request->has('start_date') && $request->has('end_date')) {
            $filters['start_date'] = $request->get('start_date');
            $filters['end_date'] = $request->get('end_date');
        } else {
            $period = $request->get('period', 'today');

            if ($period === 'today') {
                $filters['start_date'] = now()->startOfDay()->toDateString();
                $filters['end_date'] = now()->endOfDay()->toDateString();
            } else {
                $filters['start_date'] = now()->subDays((int)$period)->startOfDay()->toDateString();
                $filters['end_date'] = now()->endOfDay()->toDateString();
            }
        }

        try {
            $data = $this->analyticsService->getDashboardSummary($filters);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Analytics error: ' . $e->getMessage(), [
                'exception' => $e,
                'filters' => $filters,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load analytics data.',
            ], 500);
        }
    }

    /**
     * Get user activity profile.
     */
    public function userProfile(Request $request, int $userId): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        $request->validate([
            'user_type' => 'required|string',
        ]);

        $userType = $request->get('user_type');
        $profile = $this->analyticsService->getUserActivityProfile($userId, $userType);

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    /**
     * Get activity heatmap data.
     */
    public function heatmap(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        $days = $request->get('days', 365);
        $heatmapData = $this->analyticsService->getActivityHeatmap($days);

        return response()->json([
            'success' => true,
            'data' => $heatmapData,
        ]);
    }

    /**
     * Get recent activities for real-time updates.
     */
    public function recent(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        $hours = $request->get('hours', 1);
        $limit = $request->get('limit', 50);

        $activities = $this->activitylogService->getRecentActivities($hours, $limit);

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get activities data for API calls.
     */
    public function getActivities(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewActivityLogUi');

            $filters = $this->getFiltersFromRequest($request);
            $perPage = $request->get('per_page', 25);

            $activities = $this->activitylogService->getActivities($filters, $perPage);

            return response()->json([
                'data' => $activities->items(),
                'total' => $activities->total(),
                'per_page' => $activities->perPage(),
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('ActivityLog API Error: ' . $e->getMessage(), [
                'filters' => $filters ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch activities: ' . $e->getMessage(),
                'debug_info' => config('app.debug') ? [
                    'filters' => $filters ?? null,
                    'trace' => $e->getTraceAsString()
                ] : null
            ], 500);
        }
    }

    /**
     * Get activity details with related activities
     */
    public function getActivity($id): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        try {
            $activity = $this->activitylogService->getActivityDetail((int) $id);

            if (!$activity) {
                return response()->json(['error' => 'Activity not found'], 404);
            }

            return response()->json([
                'data' => $activity,
                'related' => $this->getRelatedActivitiesForActivity($activity)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Activity not found: ' . $e->getMessage()], 404);
        }
    }

    /**
     * Get related activities for a given activity
     */
    public function getActivityRelated($activityId): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        try {
            $activity = $this->activitylogService->getActivityDetail((int) $activityId);

            if (!$activity) {
                return response()->json(['error' => 'Activity not found'], 404);
            }

            $related = $this->getRelatedActivitiesForActivity($activity);

            return response()->json([
                'data' => $related
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Activity not found: ' . $e->getMessage()], 404);
        }
    }

    /**
     * Get search suggestions
     */
    public function getSearchSuggestions(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        try {
            $query = $request->input('q', '');
            $suggestions = [];

            if (strlen($query) >= 2) {
                $suggestions = $this->activitylogService->getSearchSuggestions($query);
            }

            return response()->json([
                'data' => $suggestions
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch suggestions'], 500);
        }
    }

    /**
     * Get filter options for the frontend.
     */
    public function getFilterOptions(): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        try {
            $causers = $this->activitylogService->getAvailableCausers();
            $subjectTypes = $this->activitylogService->getAvailableSubjectTypes();
            $eventTypes = $this->activitylogService->getEventTypesWithStyling();

            return response()->json([
                'causers' => $causers,
                'subject_types' => $subjectTypes,
                'event_types' => $eventTypes,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get filter options', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to load filter options',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get event types with styling information
     */
    public function getEventTypesWithStyling(): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        try {
            $eventTypes = $this->activitylogService->getEventTypesWithStyling();

            return response()->json([
                'success' => true,
                'data' => $eventTypes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load event types styling.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get saved views
     */
    public function getSavedViews(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        if (!config('activitylog-ui.features.saved_views', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Saved views feature is disabled.',
            ], 403);
        }

        try {
            $views = $this->activitylogService->getSavedViews($request->user()?->id);

            return response()->json([
                'data' => $views
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch saved views'], 500);
        }
    }

    /**
     * Get related activities for an activity
     */
    private function getRelatedActivitiesForActivity($activity)
    {
        if (!$activity || !$activity->subject_type || !$activity->subject_id) {
            return collect();
        }

        return $this->activitylogService->getRelatedActivities(
            $activity->subject_type,
            $activity->subject_id,
            $activity->id
        );
    }

    /**
     * Extract filters from request.
     */
    protected function getFiltersFromRequest(Request $request): array
    {
        return [
            'search' => $request->get('search'),
            'date_preset' => $request->get('date_preset'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
            'causer_type' => $request->get('causer_type'),
            'causer_id' => $this->sanitizeId($request->get('causer_id')),
            'subject_type' => $request->get('subject_type'),
            'subject_id' => $this->sanitizeId($request->get('subject_id')),
            'event_types' => $this->getArrayFromRequest($request, 'event_types'),
            'property_key' => $request->get('property_key'),
        ];
    }

    /**
     * Get array parameter from request, handling both array and single values.
     */
    private function getArrayFromRequest(Request $request, string $key): array
    {
        $value = $request->get($key);

        if (is_array($value)) {
            return array_filter($value, fn($item) => $item !== null && $item !== '');
        }

        if ($value !== null && $value !== '') {
            return [$value];
        }

        return [];
    }

    /**
     * Sanitize ID parameter to ensure proper type.
     */
    private function sanitizeId(mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (is_numeric($id)) {
            return (int) $id;
        }

        return null;
    }

    /**
     * Authorize access to activity log UI.
     */
    protected function authorize(string $ability): void
    {
        if (!config('activitylog-ui.authorization.enabled', true)) {
            return;
        }

        $gate = config('activitylog-ui.authorization.gate', 'viewActivityLogUi');

        if (method_exists($this, 'authorizeForUser')) {
            $this->authorizeForUser(request()->user(), $gate);
        } else {
            abort_unless(request()->user()?->can($gate), 403);
        }
    }
}
