<?php

namespace MuhammadSadeeq\ActivitylogUi\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use MuhammadSadeeq\ActivitylogUi\Models\Activity;

class AnalyticsService
{
    /**
     * Get dashboard summary statistics.
     */
    public function getDashboardSummary(array $filters = []): array
    {
        // Create cache key based on filters
        $filterHash = md5(serialize($filters));
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . '.dashboard_summary.' . $filterHash;
        $cacheDuration = config('activitylog-ui.analytics.cache_duration', 3600);

        return Cache::remember($cacheKey, $cacheDuration, function () use ($filters) {
            $eventTypeBreakdown = $this->getEventTypeBreakdown($filters);
            $totalActivities = $this->getTotalActivities($filters);

            // Calculate percentages for event types
            $eventTypesWithPercentages = $eventTypeBreakdown->map(function ($item) use ($totalActivities) {
                return [
                    'name' => $item['event'],
                    'label' => $item['label'],
                    'count' => $item['count'],
                    'percentage' => $totalActivities > 0 ? round(($item['count'] / $totalActivities) * 100, 1) : 0,
                    'color' => $item['color']
                ];
            });

            return [
                'stats' => [
                    'total' => number_format($totalActivities),
                    'today' => number_format($this->getActivitiesToday($filters)),
                    'active_users' => number_format($this->getActiveUsersCount($filters)),
                    'this_week' => number_format($this->getActivitiesThisWeek($filters)),
                ],
                'event_types' => $eventTypesWithPercentages->toArray(),
                'top_users' => $this->getTopUsers(10, $filters)->toArray(),
                'timeline' => $this->getRecentTimeline($filters),
                'total_activities' => $totalActivities,
                'activities_today' => $this->getActivitiesToday($filters),
                'activities_this_week' => $this->getActivitiesThisWeek($filters),
                'activities_this_month' => $this->getActivitiesThisMonth($filters),
                'popular_models' => $this->getPopularModels(10, $filters),
                'activity_trends' => $this->getActivityTrends(30, $filters),
            ];
        });
    }

    /**
     * Apply filters to a query builder.
     */
    protected function applyFilters($query, array $filters = [])
    {
        if (!empty($filters['search'])) {
            $query->where('description', 'like', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['event_types']) && is_array($filters['event_types'])) {
            $query->whereIn('event', $filters['event_types']);
        }

        if (!empty($filters['causer_id'])) {
            $causerId = is_string($filters['causer_id']) ? (int) $filters['causer_id'] : $filters['causer_id'];
            $query->where('causer_id', $causerId);
        }

        if (!empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        return $query;
    }

    /**
     * Get total activities count.
     */
    protected function getTotalActivities(array $filters = []): int
    {
        $query = Activity::query();
        $this->applyFilters($query, $filters);
        return $query->count();
    }

    /**
     * Get activities count for today.
     */
    protected function getActivitiesToday(array $filters = []): int
    {
        $query = Activity::query();
        $this->applyFilters($query, array_merge($filters, [
            'start_date' => now()->startOfDay()->toDateString(),
            'end_date' => now()->endOfDay()->toDateString(),
        ]));
        return $query->count();
    }

    /**
     * Get activities count for this week.
     */
    protected function getActivitiesThisWeek(array $filters = []): int
    {
        $query = Activity::query();
        $this->applyFilters($query, array_merge($filters, [
            'start_date' => now()->startOfWeek()->toDateString(),
            'end_date' => now()->endOfWeek()->toDateString(),
        ]));
        return $query->count();
    }

    /**
     * Get activities count for this month.
     */
    protected function getActivitiesThisMonth(array $filters = []): int
    {
        $query = Activity::query();
        $this->applyFilters($query, array_merge($filters, [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]));
        return $query->count();
    }

    /**
     * Get active users count (users who performed activities in the last 30 days).
     */
    protected function getActiveUsersCount(array $filters = []): int
    {
        $query = Activity::select('causer_type', 'causer_id')
            ->whereNotNull('causer_type')
            ->whereNotNull('causer_id')
            ->where('created_at', '>=', now()->subDays(30));

        $this->applyFilters($query, $filters);
        return $query->distinct()->count();
    }

    /**
     * Get recent timeline for the last 7 days.
     */
    protected function getRecentTimeline(array $filters = []): array
    {
        $days = [];
        $maxCount = 0;

        // Determine start and end dates from filters
        $endDate = isset($filters['end_date']) ? now()->parse($filters['end_date']) : now();
        $startDate = isset($filters['start_date']) ? now()->parse($filters['start_date']) : $endDate->copy()->subDays(6);

        // Ensure we don't exceed 90 days to prevent performance issues
        $maxDays = 90;
        if ($startDate->diffInDays($endDate) > $maxDays) {
            $startDate = $endDate->copy()->subDays($maxDays);
        }

        // Generate timeline data for each day in the range
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $query = Activity::whereDate('created_at', $currentDate->toDateString());
            $this->applyFilters($query, $filters);
            $count = $query->count();

            if ($count > $maxCount) {
                $maxCount = $count;
            }

            $days[] = [
                'date' => $currentDate->format('M j'),
                'day_name' => $currentDate->format('l'),
                'count' => $count,
                'percentage' => 0 // Will be calculated below
            ];

            $currentDate->addDay();
        }

        // Calculate percentages
        if ($maxCount > 0) {
            foreach ($days as &$day) {
                $day['percentage'] = round(($day['count'] / $maxCount) * 100, 1);
            }
        }

        return $days;
    }

    /**
     * Get top users by activity count.
     */
    protected function getTopUsers(int $limit = 10, array $filters = []): Collection
    {
        $query = Activity::select('causer_type', 'causer_id', DB::raw('count(*) as activity_count'))
            ->whereNotNull('causer_type')
            ->whereNotNull('causer_id')
            ->with('causer');

        $this->applyFilters($query, $filters);

        return $query->groupBy('causer_type', 'causer_id')
            ->orderByDesc('activity_count')
            ->limit($limit)
            ->get()
            ->filter(function ($activity) {
                return $activity->causer !== null;
            })
            ->map(function ($activity) {
                $causer = $activity->causer;
                return [
                    'id' => $activity->causer_id,
                    'name' => $causer ? ($causer->name ?? $causer->email ?? 'Unknown') : 'Unknown',
                    'email' => $causer ? ($causer->email ?? '') : '',
                    'type' => class_basename($activity->causer_type),
                    'activity_count' => $activity->activity_count,
                ];
            });
    }

    /**
     * Get most popular models by activity count.
     */
    protected function getPopularModels(int $limit = 10, array $filters = []): Collection
    {
        $query = Activity::select('subject_type', DB::raw('count(*) as activity_count'))
            ->whereNotNull('subject_type');

        $this->applyFilters($query, $filters);

        return $query->groupBy('subject_type')
            ->orderByDesc('activity_count')
            ->limit($limit)
            ->get()
            ->map(function ($activity) {
                return [
                    'type' => $activity->subject_type,
                    'name' => class_basename($activity->subject_type),
                    'activity_count' => $activity->activity_count,
                ];
            });
    }

    /**
     * Get event type breakdown.
     */
    protected function getEventTypeBreakdown(array $filters = []): Collection
    {
        $query = Activity::select('event', DB::raw('count(*) as count'))
            ->whereNotNull('event');

        $this->applyFilters($query, $filters);

        return $query->groupBy('event')
            ->orderByDesc('count')
            ->get()
            ->map(function ($activity) {
                $colors = config('activitylog-ui.analytics.chart_colors', []);

                return [
                    'event' => $activity->event,
                    'label' => ucfirst($activity->event),
                    'count' => $activity->count,
                    'color' => $colors[$activity->event] ?? '#6b7280',
                ];
            });
    }

    /**
     * Get activity trends based on the provided filters.
     */
    protected function getActivityTrends(int $days = 30, array $filters = []): array
    {
        // Use filters if provided, otherwise fallback to default period
        $endDate = isset($filters['end_date']) ? now()->parse($filters['end_date']) : now()->endOfDay();
        $startDate = isset($filters['start_date']) ? now()->parse($filters['start_date']) : $endDate->copy()->subDays($days)->startOfDay();

        $activities = Activity::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count'),
                'event'
            );

        // Apply date range and other filters
        $this->applyFilters($activities, $filters);

        $activities = $activities->groupBy('date', 'event')
            ->orderBy('date')
            ->get();

        // Generate all dates in range
        $dates = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        // Organize data by event type
        $eventTypes = $activities->pluck('event')->unique()->filter();
        $chartData = [];

        foreach ($eventTypes as $eventType) {
            $eventData = [];
            foreach ($dates as $date) {
                $activity = $activities->where('date', $date)->where('event', $eventType)->first();
                $eventData[] = [
                    'date' => $date,
                    'count' => $activity ? $activity->count : 0,
                ];
            }

            $colors = config('activitylog-ui.analytics.chart_colors', []);
            $chartData[] = [
                'label' => ucfirst($eventType),
                'data' => $eventData,
                'color' => $colors[$eventType] ?? '#6b7280',
            ];
        }

        return [
            'dates' => $dates,
            'datasets' => $chartData,
        ];
    }

    /**
     * Get user activity profile.
     */
    public function getUserActivityProfile(int $userId, string $userType): array
    {
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . ".user_profile.{$userType}.{$userId}";

        return Cache::remember($cacheKey, 1800, function () use ($userId, $userType) {
            $activities = Activity::where('causer_type', $userType)
                ->where('causer_id', $userId)
                ->with('subject')
                ->get();

            return [
                'total_activities' => $activities->count(),
                'first_activity' => $activities->min('created_at'),
                'last_activity' => $activities->max('created_at'),
                'event_breakdown' => $this->getUserEventBreakdown($activities),
                'subject_breakdown' => $this->getUserSubjectBreakdown($activities),
                'daily_activity' => $this->getUserDailyActivity($activities),
                'recent_activities' => $activities->sortByDesc('created_at')->take(10)->values(),
            ];
        });
    }

    /**
     * Get user's event type breakdown.
     */
    protected function getUserEventBreakdown(Collection $activities): Collection
    {
        return $activities->groupBy('event')
            ->map(function ($group, $event) use ($activities) {
                return [
                    'event' => $event,
                    'label' => ucfirst($event),
                    'count' => $group->count(),
                    'percentage' => round(($group->count() / $activities->count()) * 100, 1),
                ];
            })
            ->values();
    }

    /**
     * Get user's subject type breakdown.
     */
    protected function getUserSubjectBreakdown(Collection $activities): Collection
    {
        return $activities->groupBy('subject_type')
            ->map(function ($group, $subjectType) use ($activities) {
                return [
                    'type' => $subjectType,
                    'name' => class_basename($subjectType ?: 'Unknown'),
                    'count' => $group->count(),
                    'percentage' => round(($group->count() / $activities->count()) * 100, 1),
                ];
            })
            ->sortByDesc('count')
            ->values();
    }

    /**
     * Get user's daily activity for the last 30 days.
     */
    protected function getUserDailyActivity(Collection $activities): array
    {
        $last30Days = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $count = $activities->filter(function ($activity) use ($date) {
                return $activity->created_at->toDateString() === $date;
            })->count();

            $last30Days->push([
                'date' => $date,
                'count' => $count,
            ]);
        }

        return $last30Days->toArray();
    }

    /**
     * Get activity heatmap data.
     */
    public function getActivityHeatmap(int $days = 365): array
    {
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . ".heatmap.{$days}";

        return Cache::remember($cacheKey, 3600, function () use ($days) {
            $startDate = now()->subDays($days)->startOfDay();

            $activities = Activity::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('count(*) as count')
                )
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            $heatmapData = [];
            $current = $startDate->copy();
            $maxCount = $activities->max('count') ?: 1;

            while ($current <= now()) {
                $dateString = $current->toDateString();
                $count = $activities->get($dateString)?->count ?? 0;

                $heatmapData[] = [
                    'date' => $dateString,
                    'count' => $count,
                    'level' => $this->getHeatmapLevel($count, $maxCount),
                ];

                $current->addDay();
            }

            return $heatmapData;
        });
    }

    /**
     * Calculate heatmap intensity level (0-4).
     */
    protected function getHeatmapLevel(int $count, int $maxCount): int
    {
        if ($count === 0) {
            return 0;
        }

        $percentage = ($count / $maxCount) * 100;

        return match (true) {
            $percentage >= 75 => 4,
            $percentage >= 50 => 3,
            $percentage >= 25 => 2,
            default => 1,
        };
    }

    /**
     * Get anomaly detection data.
     */
    public function getAnomalies(int $days = 30): array
    {
        $dailyActivity = Activity::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $mean = $dailyActivity->avg();
        $stdDev = $this->calculateStandardDeviation($dailyActivity->values()->toArray(), $mean);
        $threshold = $mean + (2 * $stdDev); // 2 standard deviations

        $anomalies = [];
        foreach ($dailyActivity as $date => $count) {
            if ($count > $threshold) {
                $anomalies[] = [
                    'date' => $date,
                    'count' => $count,
                    'expected' => round($mean),
                    'deviation' => round(($count - $mean) / $stdDev, 2),
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Calculate standard deviation.
     */
    protected function calculateStandardDeviation(array $values, float $mean): float
    {
        $squaredDifferences = array_map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);

        $variance = array_sum($squaredDifferences) / count($values);

        return sqrt($variance);
    }
}
