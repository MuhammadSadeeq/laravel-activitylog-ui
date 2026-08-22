<?php

namespace MuhammadSadeeq\ActivitylogUi\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use MuhammadSadeeq\ActivitylogUi\Models\Activity;
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

        // Validated but not consumed: the paginator resolves 'page' itself, and it
        // silently answers 0, -1 and 'abc' with page 1. A caller who asked for a
        // specific page should be told the request was not one that could be met.
        $this->intInput($request, 'page', 1, 1, 1000000);

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

        // Validated but not consumed: the paginator resolves 'page' itself, and it
        // silently answers 0, -1 and 'abc' with page 1. A caller who asked for a
        // specific page should be told the request was not one that could be met.
        $this->intInput($request, 'page', 1, 1, 1000000);

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
        } catch (ValidationException | HttpExceptionInterface $e) {
            // A refused input is the answer, not a failure to produce one.
            // Swallowed here it became a 500 that blamed the server for a
            // parameter the caller sent.
            throw $e;
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

        // Validated but not consumed: the paginator resolves 'page' itself, and it
        // silently answers 0, -1 and 'abc' with page 1. A caller who asked for a
        // specific page should be told the request was not one that could be met.
        $this->intInput($request, 'page', 1, 1, 1000000);

            // Pins later pages to the rows that existed when the first one was
            // read, so activities recorded in between do not push the list down
            // and make the next page repeat what the user has already seen.
            //
            // Both halves are required, because the listing is ordered by
            // created_at with the key as a tiebreak and an anchor has to be a
            // prefix of that ordering. A lone id is not, and silently hid a
            // fifth of the log when the two disagreed.
            $anchor = $this->anchorFromRequest($request);

            $activities = $this->activitylogService->getActivities($filters, $perPage, $anchor);

            return response()->json([
                'data' => $activities->items(),
                'total' => $activities->total(),
                'per_page' => $activities->perPage(),
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
                // The list is ordered newest-first, so the first row of the first
                // page carries the anchor for every page after it. Echoed back
                // when one was supplied, so a client can keep using the same one.
                // Only page 1 can mint one: the first row of any later page is
                // partway down the list, and pinning to it would silently hide
                // everything above.
                'anchor_id' => $anchor['id'] ?? ($activities->currentPage() === 1 ? $activities->first()?->getKey() : null),
                // Microseconds included. A truncated anchor is not the row's
                // timestamp on a column that stores sub-second precision, so the
                // "everything at or before this" predicate excluded the whole
                // second the anchor sits in and skipped every row in it.
                'anchor_time' => $anchor['time'] ?? ($activities->currentPage() === 1
                    ? $activities->first()?->created_at?->format('Y-m-d H:i:s.u')
                    : null),
            ]);
        } catch (ValidationException | HttpExceptionInterface $e) {
            // A refused input is the answer, not a failure to produce one.
            // Swallowed here it became a 500 that blamed the server for a
            // parameter the caller sent.
            throw $e;
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
        } catch (ValidationException | HttpExceptionInterface $e) {
            // A refused input is the answer, not a failure to produce one.
            // Swallowed here it became a 500 that blamed the server for a
            // parameter the caller sent.
            throw $e;
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
        } catch (ValidationException | HttpExceptionInterface $e) {
            // A refused input is the answer, not a failure to produce one.
            // Swallowed here it became a 500 that blamed the server for a
            // parameter the caller sent.
            throw $e;
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
        } catch (ValidationException | HttpExceptionInterface $e) {
            // A refused input is the answer, not a failure to produce one.
            // Swallowed here it became a 500 that blamed the server for a
            // parameter the caller sent.
            throw $e;
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
        } catch (ValidationException | HttpExceptionInterface $e) {
            // A refused input is the answer, not a failure to produce one.
            // Swallowed here it became a 500 that blamed the server for a
            // parameter the caller sent.
            throw $e;
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
        } catch (ValidationException | HttpExceptionInterface $e) {
            // A refused input is the answer, not a failure to produce one.
            // Swallowed here it became a 500 that blamed the server for a
            // parameter the caller sent.
            throw $e;
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
        } catch (ValidationException | HttpExceptionInterface $e) {
            // A refused input is the answer, not a failure to produce one.
            // Swallowed here it became a 500 that blamed the server for a
            // parameter the caller sent.
            throw $e;
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
        if (!$request->exists($key) || $request->input($key) === null || $request->input($key) === '') {
            // The configured default is still clamped. It is the package's own
            // value, not the caller's, so refusing the request over it would
            // report someone else's mistake as theirs.
            return (int) max($min, min($max, $default));
        }

        $value = $request->input($key);

        if (is_array($value) || !is_numeric($value) || (string) (int) $value !== trim((string) $value)) {
            $this->rejectInput($key, "The {$key} parameter must be a whole number between {$min} and {$max}.");
        }

        $value = (int) $value;

        if ($value < $min || $value > $max) {
            $this->rejectInput($key, "The {$key} parameter must be between {$min} and {$max}. You asked for {$value}.");
        }

        return $value;
    }

    /**
     * Read a plain string from the request, rejecting arrays and other shapes.
     */
    protected function stringInput(Request $request, string $key, string $default = ''): string
    {
        if (!$request->exists($key)) {
            return $default;
        }

        $value = $request->input($key);

        if ($value === null) {
            return $default;
        }

        if (!is_scalar($value)) {
            $this->rejectInput($key, "The {$key} parameter must be a single value.");
        }

        return (string) $value;
    }

    /**
     * Refuse a request rather than quietly answering a different one.
     *
     * Everything here used to be clamped or dropped: per_page=999999 silently
     * became 100, a malformed causer id became no causer filter at all, and the
     * response said nothing about either. For a page whose whole purpose is to
     * report what happened, quietly widening a filter is the worst of the
     * options — it shows more of the audit log than was asked for and looks like
     * a complete answer.
     *
     * JSON callers get the framework's standard validation payload. Anything
     * else gets a 422 page: a ValidationException would redirect back for an
     * HTML request, and since the offending value is in the URL of the page
     * being requested, that redirects into itself.
     */
    protected function rejectInput(string $key, string $message): never
    {
        if (request()->expectsJson()) {
            throw ValidationException::withMessages([$key => $message]);
        }

        abort(422, $message);
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
            // Passed through raw: normalizeFilters is the single place that
            // decides what an id may be, and sanitising here first turned an
            // unusable one into null before it could be refused.
            'causer_id' => $request->input('causer_id'),
            'subject_type' => $request->input('subject_type'),
            'subject_id' => $request->input('subject_id'),
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
            if (!array_key_exists($key, $filters) || $filters[$key] === null) {
                continue;
            }

            $value = $filters[$key];

            // A scope typed ?string would raise a TypeError on an array, so this
            // shape was being replaced with null — which reads as "no filter" and
            // answers with the unfiltered log.
            if (!is_scalar($value)) {
                $this->rejectInput($key, "The {$key} filter must be a single value.");
            }

            $filters[$key] = (string) $value;
        }

        if (array_key_exists('event_types', $filters) && $filters['event_types'] !== null) {
            $types = is_array($filters['event_types']) ? $filters['event_types'] : [$filters['event_types']];
            $types = array_values(array_filter($types, fn ($type) => $type !== null && $type !== ''));

            foreach ($types as $type) {
                // A nested array reaches whereIn() and becomes an unbindable
                // parameter; an over-long one cannot match a stored event.
                if (!is_scalar($type) || mb_strlen((string) $type) > 191) {
                    $this->rejectInput('event_types', 'Each event type must be a single value of at most 191 characters.');
                }
            }

            // These become whereIn bindings, and a few thousand of them exceed
            // what SQLite and others accept. Truncating to the first hundred
            // silently broadened the filter instead.
            if (count($types) > 100) {
                $this->rejectInput('event_types', 'At most 100 event types can be filtered on at once. You sent ' . count($types) . '.');
            }

            $filters['event_types'] = array_map(fn ($type) => (string) $type, $types);
        }

        foreach (['causer_id', 'subject_id'] as $key) {
            if (!array_key_exists($key, $filters) || $filters[$key] === null || $filters[$key] === '') {
                continue;
            }

            $id = $this->sanitizeId($filters[$key]);

            // Dropping an unusable id was the worst case of all: the caller asked
            // for one causer's activity and got everyone's, with nothing saying so.
            if ($id === null) {
                $this->rejectInput($key, "The {$key} filter is not a usable identifier.");
            }

            $filters[$key] = $id;
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
     * Read the pagination anchor, which is a (created_at, key) pair.
     *
     * Both halves must be present and usable, or there is no anchor: filtering
     * on half of a compound ordering is what dropped rows before.
     *
     * @return array{time: string, id: int|string}|null
     */
    protected function anchorFromRequest(Request $request): ?array
    {
        $rawId = $request->input('anchor_id');
        $rawTime = $request->input('anchor_time');

        $missingId = $rawId === null || $rawId === '';
        $missingTime = $rawTime === null || $rawTime === '';

        if ($missingId && $missingTime) {
            return null;
        }

        if ($missingId || $missingTime) {
            $this->rejectInput('anchor_id', 'The anchor_id and anchor_time parameters must be supplied together.');
        }

        $id = $this->sanitizeId($rawId);

        if ($id === null) {
            $this->rejectInput('anchor_id', 'The anchor_id parameter is not a usable identifier.');
        }

        // Unlike a causer or subject id, which is polymorphic and may be anything
        // the host model uses, this one addresses the activity's own key — and
        // this UI knows what type that is. On an integer key MySQL coerces
        // 'abc' to 0 rather than complaining, so an unusable anchor came back as
        // a page with nothing on it instead of an error.
        if ($id !== null && !is_int($id) && in_array((new Activity)->getKeyType(), ['int', 'integer'], true)) {
            $this->rejectInput('anchor_id', 'The anchor_id parameter must be an integer.');
        }

        // Fractional seconds optional, so a client holding an anchor minted
        // before they were sent still works, and a log stored with sub-second
        // precision can send back the timestamp it actually has.
        if (!is_string($rawTime) || preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(\.\d{1,6})?$/', $rawTime) !== 1) {
            $this->rejectInput('anchor_time', 'The anchor_time parameter must be a timestamp of the form Y-m-d H:i:s, optionally with fractional seconds.');
        }

        return ['time' => str_replace('T', ' ', $rawTime), 'id' => $id];
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
