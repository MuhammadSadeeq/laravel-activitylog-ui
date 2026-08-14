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
        $view = $this->stringInput($request, 'view', (string) config('activitylog-ui.ui.default_view', 'table'));
        $perPage = $this->intInput($request, 'per_page', (int) config('activitylog-ui.ui.default_per_page', 25), 1, $this->maxPerPage());

        $data = ['filters' => $filters, 'view' => $view, 'perPage' => $perPage];

        // Activities, filter options and saved views are all fetched over the API
        // once the page is up, so querying them here only duplicated that work on
        // every render. It also put getAvailableCausers() — a full scan of the log
        // — on the request path with no error handling around it, which is how a
        // single bad cache entry took the whole dashboard down (issue #12).
        //
        // Views published before v2.1 may still reference the old variables, so
        // they are supplied when a published copy is present. Deprecated: this
        // fallback will be dropped in the next major version.
        if ($this->hasPublishedDashboardView()) {
            $data += $this->legacyViewData($request, $filters, $view, $perPage);
        }

        return view('activitylog-ui::pages.dashboard', $data);
    }

    /**
     * Whether the host application has published its own copy of the dashboard view.
     */
    protected function hasPublishedDashboardView(): bool
    {
        return is_file(resource_path('views/vendor/activitylog-ui/pages/dashboard.blade.php'));
    }

    /**
     * @deprecated Prefetched view data kept only for views published before v2.1.
     *
     * @return array<string, mixed>
     */
    protected function legacyViewData(Request $request, array $filters, string $view, mixed $perPage): array
    {
        return [
            'data' => $view === 'timeline'
                ? $this->activitylogService->getTimelineActivities($filters, $perPage)
                : $this->activitylogService->getActivities($filters, $perPage),
            'filterOptions' => [
                'causers' => $this->activitylogService->getAvailableCausers(),
                'subject_types' => $this->activitylogService->getAvailableSubjectTypes(),
                'event_types' => $this->activitylogService->getAvailableEventTypes(),
                'date_presets' => config('activitylog-ui.filters.date_presets', []),
            ],
            'savedViews' => config('activitylog-ui.features.saved_views', true)
                ? $this->activitylogService->getSavedViews($request->user()?->id)
                : [],
        ];
    }

    /**
     * Get activities data for AJAX requests.
     */
    public function getData(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        $filters = $this->getFiltersFromRequest($request);
        $view = $this->stringInput($request, 'view', (string) config('activitylog-ui.ui.default_view', 'table'));
        $perPage = $this->intInput($request, 'per_page', (int) config('activitylog-ui.ui.default_per_page', 25), 1, $this->maxPerPage());

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
                'has_changes' => $activity->hasAttributeChanges(),
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

        $query = $request->input('query');
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
            $request->input('filters'),
            $request->input('name'),
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
            $request->input('view_id'),
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

        // Reuse standard filter parsing so analytics stays consistent with other views.
        $filters = $this->getFiltersFromRequest($request);

        // Keep existing behavior: if dates are not fully provided, derive from period.
        if (empty($filters['start_date']) || empty($filters['end_date'])) {
            $period = $this->stringInput($request, 'period', 'today');

            // Anything that is not a day count falls back to today, rather than
            // casting to 0 and quietly returning a single day labelled otherwise.
            $days = is_numeric($period) ? (int) max(0, min(3650, (int) $period)) : 0;

            $filters['start_date'] = now()->subDays($days)->startOfDay()->toDateString();
            $filters['end_date'] = now()->endOfDay()->toDateString();
        }

        try {
            $data = $this->analyticsService->getDashboardSummary($filters);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
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
    public function userProfile(Request $request, int|string $userId): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        $request->validate([
            'user_type' => 'required|string',
        ]);

        $userType = $this->stringInput($request, 'user_type');
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

        $days = $this->intInput($request, 'days', 365, 1, 3650);
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

        $hours = $this->intInput($request, 'hours', 1, 1, 8760);
        $limit = $this->intInput($request, 'limit', 50, 1, 500);

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
        $this->authorize('viewActivityLogUi');

        try {
            $filters = $this->getFiltersFromRequest($request);
            $perPage = $this->intInput($request, 'per_page', 25, 1, $this->maxPerPage());

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
        } catch (\Throwable $e) {
            // Log the error for debugging
            Log::error('ActivityLog API Error: ' . $e->getMessage(), [
                'filters' => $filters ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch activities.',
                'message' => config('app.debug') ? $e->getMessage() : null,
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
        } catch (\Throwable $e) {
            Log::error('Failed to load activity detail', [
                'activity_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to load activity.',
                'message' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
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
        } catch (\Throwable $e) {
            Log::error('Failed to load related activities', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to load related activities.',
                'message' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get search suggestions
     */
    public function getSearchSuggestions(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        try {
            $query = $this->stringInput($request, 'q');
            $suggestions = [];

            if (strlen($query) >= 2) {
                $suggestions = $this->activitylogService->getSearchSuggestions($query);
            }

            return response()->json([
                'data' => $suggestions
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch search suggestions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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
        } catch (\Throwable $e) {
            Log::error('Failed to get filter options', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to load filter options',
                'message' => config('app.debug') ? $e->getMessage() : null,
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
        } catch (\Throwable $e) {
            Log::error('Failed to load event types styling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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
        } catch (\Throwable $e) {
            Log::error('Failed to fetch saved views', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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
     * Largest per_page the UI is allowed to request.
     *
     * Derived from the configured options so a host that offers 1000 rows gets
     * 1000, rather than silently receiving 500 while the selector still says 1000.
     */
    protected function maxPerPage(): int
    {
        $options = array_filter((array) config('activitylog-ui.ui.per_page_options', [10, 25, 50, 100]), 'is_numeric');
        $configured = $options ? (int) max($options) : 100;

        // Still bounded: an option list is a UI affordance, not a licence to load
        // the entire table in one request.
        return (int) min(1000, max(1, $configured));
    }

    /**
     * Read a bounded integer from the request.
     *
     * These values are handed straight to int-typed service parameters, so an
     * array or a non-numeric string produced an uncaught TypeError — a 500 on a
     * public-by-default route from nothing more than `?per_page[]=25`.
     */
    protected function intInput(Request $request, string $key, int $default, int $min, int $max): int
    {
        $value = $request->input($key, $default);

        // The default is clamped too: a configured default_per_page above the cap
        // otherwise slipped through whenever the request value was malformed.
        if (is_array($value) || !is_numeric($value)) {
            return (int) max($min, min($max, $default));
        }

        return (int) max($min, min($max, (int) $value));
    }

    /**
     * Read a plain string from the request, rejecting arrays and other shapes.
     */
    protected function stringInput(Request $request, string $key, string $default = ''): string
    {
        $value = $request->input($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Extract filters from request.
     */
    protected function getFiltersFromRequest(Request $request): array
    {
        // Every one of these ends up in a scope typed ?string. Passing the raw
        // input meant `?search[]=x`, `?date_preset[]=today` and friends reached
        // those scopes as arrays and raised a TypeError, so normalise the shape
        // here rather than at each call site.
        return $this->normalizeFilters([
            'search' => $request->input('search'),
            'date_preset' => $request->input('date_preset'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'causer_type' => $request->input('causer_type'),
            'causer_id' => $this->sanitizeId($request->input('causer_id')),
            'subject_type' => $request->input('subject_type'),
            'subject_id' => $this->sanitizeId($request->input('subject_id')),
            'event_types' => $this->getArrayFromRequest($request, 'event_types'),
            'property_key' => $request->input('property_key'),
        ]);
    }

    /**
     * Coerce a filter set into the shapes the query layer accepts.
     *
     * Public so the export endpoint, which receives filters nested inside a JSON
     * body and so never passes through getFiltersFromRequest(), can use it too.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters): array
    {
        $strings = ['search', 'date_preset', 'start_date', 'end_date', 'causer_type', 'subject_type', 'property_key'];

        foreach ($strings as $key) {
            if (!array_key_exists($key, $filters)) {
                continue;
            }

            $value = $filters[$key];
            $filters[$key] = is_scalar($value) ? (string) $value : null;
        }

        if (array_key_exists('event_types', $filters)) {
            $types = is_array($filters['event_types']) ? $filters['event_types'] : [$filters['event_types']];

            // Flat list of non-empty strings: a nested array reaches whereIn() and
            // becomes an unbindable parameter.
            $filters['event_types'] = array_slice(array_values(array_filter(
                array_map(fn ($type) => is_scalar($type) ? (string) $type : null, $types),
                fn ($type) => $type !== null && $type !== '' && mb_strlen($type) <= 191
            )),
                // Bounded: these become whereIn bindings, and a few thousand of
                // them exceed what SQLite and others accept, failing the request
                // before the export controller's own error handling.
                0, 100);
        }

        foreach (['causer_id', 'subject_id'] as $key) {
            if (array_key_exists($key, $filters)) {
                $filters[$key] = $this->sanitizeId($filters[$key]);
            }
        }

        return $filters;
    }

    /**
     * Get array parameter from request, handling both array and single values.
     */
    private function getArrayFromRequest(Request $request, string $key): array
    {
        $value = $request->input($key);

        if (is_array($value)) {
            return array_filter($value, fn($item) => $item !== null && $item !== '');
        }

        if ($value !== null && $value !== '') {
            return [$value];
        }

        return [];
    }

    /**
     * Normalise a causer or subject id from the request.
     *
     * Host applications key their models on auto-increment integers, UUIDs or
     * ULIDs, so a non-numeric id is a legitimate value and is passed through
     * rather than discarded. The service layer and the model scopes already
     * accept both.
     */
    private function sanitizeId(mixed $id): int|string|null
    {
        if (is_int($id)) {
            return $id;
        }

        if (!is_string($id) || $id === '') {
            return null;
        }

        // Only a canonical integer literal is treated as an integer key. is_numeric()
        // is too loose here: it accepts '1e3' and '5.9', and it also matches an
        // all-digit ULID, which would then be cast to a completely different value.
        if (preg_match('/^-?\d+$/', $id) === 1 && $id === (string) (int) $id) {
            return (int) $id;
        }

        // Otherwise only accept shapes that can plausibly be a key. Passing arbitrary
        // text through reaches the driver, and an integer key column then behaves
        // three different ways: MySQL coerces ('5abc' matches 5), PostgreSQL errors,
        // SQLite matches nothing.
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) === 1
            || preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $id) === 1
                ? $id
                : null;
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
