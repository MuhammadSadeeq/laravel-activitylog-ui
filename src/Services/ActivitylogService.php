<?php

namespace MuhammadSadeeq\ActivitylogUi\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use MuhammadSadeeq\ActivitylogUi\Models\Activity;

class ActivitylogService
{
    /**
     * Get filtered activities with pagination.
     */
    public function getActivities(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Activity::query()
            ->with(config('activitylog-ui.performance.eager_load_relations', ['causer', 'subject']))
            ->latest('created_at');

        $query = $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Get activities for timeline view.
     */
    public function getTimelineActivities(array $filters = [], int $perPage = 25): array
    {
        $activities = $this->getActivities($filters, $perPage);

        return $this->groupActivitiesByDate($activities);
    }

    /**
     * Apply all filters to the query.
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        // Search filter
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Date filters
        if (!empty($filters['date_preset']) && $filters['date_preset'] !== 'custom') {
            $query->datePreset($filters['date_preset']);
        } elseif (!empty($filters['start_date']) || !empty($filters['end_date'])) {
            $query->dateRange($filters['start_date'] ?? null, $filters['end_date'] ?? null);
        }

        // Causer filters
        if (!empty($filters['causer_type']) || !empty($filters['causer_id'])) {
            $causerId = isset($filters['causer_id']) && $filters['causer_id'] !== ''
                ? (is_numeric($filters['causer_id']) ? (int) $filters['causer_id'] : $filters['causer_id'])
                : null;
            $query->byCauser($filters['causer_type'] ?? null, $causerId);
        }

        // Subject filters
        if (!empty($filters['subject_type']) || !empty($filters['subject_id'])) {
            $subjectId = isset($filters['subject_id']) && $filters['subject_id'] !== ''
                ? (is_numeric($filters['subject_id']) ? (int) $filters['subject_id'] : $filters['subject_id'])
                : null;
            $query->bySubject($filters['subject_type'] ?? null, $subjectId);
        }

        // Event type filters
        if (!empty($filters['event_types']) && is_array($filters['event_types'])) {
            $query->byEventTypes($filters['event_types']);
        }

        // Property filters
        if (!empty($filters['property_key'])) {
            $query->whereJsonContains('properties', [$filters['property_key'] => null]);
        }

        return $query;
    }

    /**
     * Group activities by date for timeline view.
     */
    protected function groupActivitiesByDate(LengthAwarePaginator $activities): array
    {
        $grouped = [];

        foreach ($activities->items() as $activity) {
            $date = $activity->created_at->toDateString();
            $dateLabel = $this->getDateLabel($activity->created_at);

            if (!isset($grouped[$date])) {
                $grouped[$date] = [
                    'date' => $date,
                    'label' => $dateLabel,
                    'activities' => [],
                ];
            }

            $grouped[$date]['activities'][] = $activity;
        }

        return [
            'groups' => array_values($grouped),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
            ],
        ];
    }

    /**
     * Get human-readable date label.
     */
    protected function getDateLabel(\Carbon\Carbon $date): string
    {
        $now = now();

        if ($date->isToday()) {
            return 'Today';
        }

        if ($date->isYesterday()) {
            return 'Yesterday';
        }

        if ($date->diffInDays($now) <= 7) {
            return $date->format('l'); // Day name
        }

        if ($date->year === $now->year) {
            return $date->format('F j'); // Month Day
        }

        return $date->format('F j, Y'); // Month Day, Year
    }

    /**
     * Get available causers for filtering.
     */
    public function getAvailableCausers(): Collection
    {
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . '.causers';

        return Cache::remember($cacheKey, 3600, function () {
            return Activity::select('causer_type', 'causer_id')
                ->whereNotNull('causer_type')
                ->whereNotNull('causer_id')
                ->with('causer')
                ->distinct()
                ->get()
                ->filter(function ($activity) {
                    return $activity->causer !== null;
                })
                ->map(function ($activity) {
                    return [
                        'id' => $activity->causer_id,
                        'type' => $activity->causer_type,
                        'name' => $activity->causer_name,
                        'label' => $activity->causer_name . ' (' . class_basename($activity->causer_type) . ')',
                    ];
                })
                ->unique('id')
                ->values();
        });
    }

    /**
     * Get available subject types for filtering.
     */
    public function getAvailableSubjectTypes(): Collection
    {
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . '.subject_types';

        return Cache::remember($cacheKey, 3600, function () {
            return Activity::select('subject_type')
                ->whereNotNull('subject_type')
                ->distinct()
                ->pluck('subject_type')
                ->map(function ($type) {
                    return [
                        'value' => $type,
                        'label' => class_basename($type),
                        'full_name' => $type,
                    ];
                })
                ->values();
        });
    }

    /**
     * Get available event types for filtering.
     */
    public function getAvailableEventTypes(): Collection
    {
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . '.event_types';

        return Cache::remember($cacheKey, 3600, function () {
            return Activity::select('event')
                ->whereNotNull('event')
                ->distinct()
                ->pluck('event')
                ->map(function ($event) {
                    return [
                        'value' => $event,
                        'label' => ucfirst($event),
                    ];
                })
                ->values();
        });
    }

    /**
     * Get available event types with styling information for UI components.
     */
    public function getEventTypesWithStyling(): Collection
    {
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . '.event_types_with_styling';

        return Cache::remember($cacheKey, 3600, function () {
            $eventTypes = Activity::select('event')
                ->whereNotNull('event')
                ->distinct()
                ->pluck('event')
                ->values();

            return $eventTypes->map(function ($event, $index) {
                $styling = $this->generateEventTypeStyling($event, $index);

                return [
                    'value' => $event,
                    'label' => ucfirst($event),
                    'colors' => $styling['colors'],
                    'gradient' => $styling['gradient'],
                    'icon' => $styling['icon'],
                    'badge_classes' => $styling['badge_classes'],
                    'timeline_classes' => $styling['timeline_classes'],
                ];
            });
        });
    }

    /**
     * Generate consistent styling for an activity type.
     */
    protected function generateEventTypeStyling(string $eventType, int $index): array
    {
        // Get predefined colors from config first
        $configColors = config('activitylog-ui.analytics.chart_colors', []);

        if (isset($configColors[$eventType])) {
            // Use configured color if available
            $baseColor = $this->getColorName($configColors[$eventType]);
        } else {
            // Generate color based on event type characteristics
            $baseColor = $this->selectColorForEventType($eventType, $index);
        }

        return [
            'colors' => [
                'primary' => $baseColor,
                'light' => $this->getColorShade($baseColor, 100),
                'medium' => $this->getColorShade($baseColor, 500),
                'dark' => $this->getColorShade($baseColor, 800),
            ],
            'gradient' => [
                'from' => $this->getColorShade($baseColor, 500),
                'to' => $this->getColorShade($baseColor, 600),
                'dark_from' => $this->getColorShade($baseColor, 400),
                'dark_to' => $this->getColorShade($baseColor, 500),
            ],
            'icon' => $this->selectIconForEventType($eventType),
            'badge_classes' => $this->generateBadgeClasses($baseColor),
            'timeline_classes' => $this->generateTimelineClasses($baseColor),
        ];
    }

    /**
     * Select appropriate color for event type based on semantic meaning.
     */
    protected function selectColorForEventType(string $eventType, int $fallbackIndex): string
    {
        // Semantic color mapping for common event types
        $semanticColors = [
            'created' => 'green',
            'updated' => 'blue',
            'deleted' => 'red',
            'restored' => 'yellow',
            'login' => 'purple',
            'logout' => 'indigo',
            'system' => 'pink',
            'error' => 'red',
            'warning' => 'amber',
            'info' => 'blue',
            'success' => 'green',
            'failed' => 'red',
            'completed' => 'green',
            'started' => 'blue',
            'cancelled' => 'gray',
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'published' => 'green',
            'drafted' => 'gray',
            'archived' => 'slate',
        ];

        // Check for exact match first
        if (isset($semanticColors[$eventType])) {
            return $semanticColors[$eventType];
        }

        // Check for partial matches (e.g., "user_login" contains "login")
        foreach ($semanticColors as $keyword => $color) {
            if (str_contains($eventType, $keyword)) {
                return $color;
            }
        }

        // Fallback to a color palette rotation
        $colorPalette = [
            'blue', 'green', 'purple', 'pink', 'indigo', 'cyan',
            'teal', 'emerald', 'lime', 'amber', 'orange', 'rose'
        ];

        return $colorPalette[$fallbackIndex % count($colorPalette)];
    }

    /**
     * Select appropriate icon for event type.
     */
    protected function selectIconForEventType(string $eventType): string
    {
        $iconMapping = [
            'created' => 'M12 6v6m0 0v6m0-6h6m-6 0H6',
            'updated' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
            'deleted' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
            'restored' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
            'login' => 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1',
            'logout' => 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
            'system' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        ];

        // Check for exact match
        if (isset($iconMapping[$eventType])) {
            return $iconMapping[$eventType];
        }

        // Check for partial matches
        foreach ($iconMapping as $keyword => $icon) {
            if (str_contains($eventType, $keyword)) {
                return $icon;
            }
        }

        // Default icon
        return 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
    }

    /**
     * Convert hex color to color name (simplified mapping).
     */
    protected function getColorName(string $hexColor): string
    {
        $colorMap = [
            '#10b981' => 'green',
            '#3b82f6' => 'blue',
            '#ef4444' => 'red',
            '#f59e0b' => 'yellow',
            '#8b5cf6' => 'purple',
            '#6366f1' => 'indigo',
            '#ec4899' => 'pink',
        ];

        return $colorMap[$hexColor] ?? 'gray';
    }

    /**
     * Get color shade for Tailwind classes.
     */
    protected function getColorShade(string $color, int $shade): string
    {
        return "{$color}-{$shade}";
    }

    /**
     * Generate badge classes for an activity type.
     */
    protected function generateBadgeClasses(string $color): string
    {
        return "bg-{$color}-100 dark:bg-{$color}-900/30 text-{$color}-800 dark:text-{$color}-300 border-{$color}-200 dark:border-{$color}-800";
    }

    /**
     * Generate timeline classes for an activity type.
     */
    protected function generateTimelineClasses(string $color): string
    {
        return "bg-gradient-to-br from-{$color}-500 to-{$color}-600 dark:from-{$color}-400 dark:to-{$color}-500";
    }

    /**
     * Get recent activities for real-time updates.
     */
    public function getRecentActivities(int $hours = 1, int $limit = 50): Collection
    {
        return Activity::with(['causer', 'subject'])
            ->recent($hours)
            ->limit($limit)
            ->latest()
            ->get();
    }

    /**
     * Search activities with autocomplete suggestions.
     */
    public function searchWithSuggestions(string $query): array
    {
        $activities = Activity::search($query)
            ->with(['causer', 'subject'])
            ->limit(10)
            ->get();

        $suggestions = [
            'descriptions' => Activity::where('description', 'like', "%{$query}%")
                ->distinct()
                ->limit(5)
                ->pluck('description')
                ->toArray(),
            'causers' => Activity::whereHas('causer', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->with('causer')
            ->limit(5)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->causer_id,
                    'name' => $activity->causer_name,
                    'type' => $activity->causer_type,
                ];
            })
            ->unique('id')
            ->values()
            ->toArray(),
        ];

        return [
            'activities' => $activities,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Get activity detail with enhanced information.
     */
    public function getActivityDetail(int|string $id): ?Activity
    {
        return Activity::with(['causer', 'subject'])
            ->find((int) $id);
    }

    /**
     * Save a view configuration for later use.
     */
    public function saveView(array $filters, string $name, ?int $userId = null): array
    {
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . ".saved_views.{$userId}";
        $savedViews = Cache::get($cacheKey, []);

        $view = [
            'id' => uniqid(),
            'name' => $name,
            'filters' => $filters,
            'created_at' => now()->toISOString(),
        ];

        $savedViews[] = $view;

        // Limit number of saved views
        $maxViews = config('activitylog-ui.filters.max_saved_views', 10);
        if (count($savedViews) > $maxViews) {
            $savedViews = array_slice($savedViews, -$maxViews);
        }

        Cache::put($cacheKey, $savedViews, 86400 * 30); // 30 days

        return $view;
    }

    /**
     * Get saved views for a user.
     */
    public function getSavedViews(?int $userId = null): array
    {
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . ".saved_views.{$userId}";
        return Cache::get($cacheKey, []);
    }

    /**
     * Delete a saved view.
     */
    public function deleteSavedView(string $viewId, ?int $userId = null): bool
    {
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . ".saved_views.{$userId}";
        $savedViews = Cache::get($cacheKey, []);

        $savedViews = array_filter($savedViews, function ($view) use ($viewId) {
            return $view['id'] !== $viewId;
        });

        Cache::put($cacheKey, array_values($savedViews), 86400 * 30);

        return true;
    }

    /**
     * Get search suggestions for autocomplete.
     */
    public function getSearchSuggestions(string $query): Collection
    {
        $suggestions = collect();

        if (strlen($query) >= 2) {
            // Get causer suggestions
            $causers = Activity::whereNotNull('causer_id')
                ->with('causer')
                ->get()
                ->pluck('causer')
                ->filter()
                ->unique('id')
                ->filter(function ($causer) use ($query) {
                    return stripos($causer->name ?? '', $query) !== false ||
                           stripos($causer->email ?? '', $query) !== false;
                })
                ->take(5)
                ->map(function ($causer) {
                    return [
                        'value' => $causer->name,
                        'label' => $causer->name . ' (' . $causer->email . ')',
                        'type' => 'User'
                    ];
                });

            // Get description suggestions
            $descriptions = Activity::where('description', 'like', "%{$query}%")
                ->distinct()
                ->pluck('description')
                ->take(5)
                ->map(function ($description) {
                    return [
                        'value' => $description,
                        'label' => $description,
                        'type' => 'Description'
                    ];
                });

            // Get subject type suggestions
            $subjectTypes = Activity::where('subject_type', 'like', "%{$query}%")
                ->distinct()
                ->pluck('subject_type')
                ->take(3)
                ->map(function ($type) {
                    return [
                        'value' => $type,
                        'label' => class_basename($type),
                        'type' => 'Model'
                    ];
                });

            $suggestions = $causers->concat($descriptions)->concat($subjectTypes);
        }

        return $suggestions->take(10);
    }

    /**
     * Get related activities for a given subject.
     */
    public function getRelatedActivities(string $subjectType, int $subjectId, int $excludeId = null): Collection
    {
        $query = Activity::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->with(['causer'])
            ->orderBy('created_at', 'desc')
            ->take(10);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }
}
