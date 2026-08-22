<?php

namespace MuhammadSadeeq\ActivitylogUi\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . '.' . self::ANALYTICS_CACHE_VERSION . '.' . Activity::sourceFingerprint() . '.dashboard_summary.' . $filterHash;
        $cacheDuration = config('activitylog-ui.analytics.cache_duration', 3600);

        $cached = $this->readCachedArray($cacheKey, ['stats', 'event_types', 'total_activities']);

        if ($cached !== null) {
            return $cached;
        }

        $summary = (function () use ($filters) {
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
                // toArray(): this was a Collection object inside an otherwise plain
                // cached array, so the payload still depended on a class being
                // loadable at unserialize time.
                'popular_models' => $this->getPopularModels(10, $filters)->toArray(),
                'activity_trends' => $this->getActivityTrends(30, $filters),
            ];
        })();

        $this->writeCachedArray($cacheKey, $summary, $cacheDuration);

        return $summary;
    }

    /**
     * Version segment for the analytics cache keys.
     *
     * These payloads used to contain Collections, Eloquent models and Carbon
     * instances. Without a version bump an entry written before that changed
     * would still be read back and served, since it is an array either way.
     *
     * v3: analytics now filters exactly as the activity list does. The filter
     * array — and so its hash — is unchanged, but the numbers it produces are
     * not, so a v2 entry answers the same key with the old, narrower result.
     */
    protected const ANALYTICS_CACHE_VERSION = 'v3';

    /**
     * Read a cached analytics array, or null when there is nothing usable.
     *
     * @param  list<string>  $requiredKeys
     * @return array<string, mixed>|null
     */
    protected function readCachedArray(string $key, array $requiredKeys = []): ?array
    {
        try {
            $cached = Cache::get($key);

            if (is_array($cached) && $this->isPlainData($cached)) {
                foreach ($requiredKeys as $required) {
                    if (!array_key_exists($required, $cached)) {
                        Cache::forget($key);

                        return null;
                    }
                }

                return $cached;
            }

            if ($cached !== null) {
                Cache::forget($key);
            }
        } catch (\Throwable $e) {
            Log::warning('Activity log UI analytics cache read failed; falling back to a live query.', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Write a cached analytics array, tolerating an unavailable store.
     */
    protected function writeCachedArray(string $key, array $value, int $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (\Throwable $e) {
            Log::warning('Activity log UI analytics cache write failed.', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Whether a value is made only of scalars, nulls and arrays of the same.
     */
    protected function isPlainData(mixed $value): bool
    {
        if ($value === null || is_scalar($value)) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!$this->isPlainData($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply filters to a query builder.
     *
     * Delegates to the list's own filtering rather than keeping a reduced copy.
     * The copy ignored causer_type, subject_id and property_key, and searched
     * only `description`, so picking one causer counted another's activities and
     * searching an email found rows in the table but nothing in analytics.
     */
    protected function applyFilters($query, array $filters = [])
    {
        return app(ActivitylogService::class)->applyFilters($query, $filters);
    }

    /**
     * Merge an explicit date window over the caller's filters.
     *
     * date_preset takes precedence over start_date/end_date in the shared filter
     * logic, so a preset left in place would override the very window these
     * counts are asking for.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function withDateWindow(array $filters, string $start, string $end): array
    {
        return array_merge($filters, [
            'date_preset' => null,
            'start_date' => $start,
            'end_date' => $end,
        ]);
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
        $this->applyFilters($query, $this->withDateWindow($filters, now()->startOfDay()->toDateString(), now()->endOfDay()->toDateString()));
        return $query->count();
    }

    /**
     * Get activities count for this week.
     */
    protected function getActivitiesThisWeek(array $filters = []): int
    {
        $query = Activity::query();
        $this->applyFilters($query, $this->withDateWindow($filters, now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()));
        return $query->count();
    }

    /**
     * Get activities count for this month.
     */
    protected function getActivitiesThisMonth(array $filters = []): int
    {
        $query = Activity::query();
        $this->applyFilters($query, $this->withDateWindow($filters, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()));
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

        // One grouped query for the whole range. This used to be a COUNT per day
        // with the full filter set re-applied each time, so a 90-day window cost
        // 91 round trips — and with a search term each of those carried a
        // whereHasMorph across every causer table.
        $expression = $this->dateExpression();

        $query = Activity::query()
            ->selectRaw("{$expression} as day, count(*) as aggregate")
            // Half-open, not whereBetween(startOfDay, endOfDay). Bindings are
            // formatted to whole seconds, so endOfDay's .999999 became :59 and a
            // row stored at 23:59:59.5 fell outside a range that should contain
            // it — a row the per-day count it replaced did include.
            ->where('created_at', '>=', $startDate->copy()->startOfDay())
            ->where('created_at', '<', $endDate->copy()->startOfDay()->addDay());

        $this->applyFilters($query, $filters);

        $counts = $query->groupBy(DB::raw($expression))
            ->get()
            // get() rather than pluck(): a driver may hand back a DateTime for a
            // date column — SQL Server does with SQLSRV_ATTR_FETCHES_DATETIME_TYPE
            // — and pluck would use the object as an array key before this could
            // normalise it.
            ->mapWithKeys(fn ($row) => [$this->dayKey($row->day) => (int) $row->aggregate]);

        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $count = $counts[$currentDate->toDateString()] ?? 0;

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
     * A driver-appropriate SQL expression for the date part of created_at.
     *
     * DATE() is MySQL, MariaDB and SQLite; PostgreSQL and SQL Server have no
     * such function, so every grouped-by-day chart here was a syntax error on
     * those two.
     */
    protected function dateExpression(string $column = 'created_at'): string
    {
        return match (Activity::query()->getConnection()->getDriverName()) {
            'pgsql', 'sqlsrv' => "CAST({$column} AS DATE)",
            default => "DATE({$column})",
        };
    }

    /**
     * Normalise whatever a driver returns for a grouped date into 'Y-m-d'.
     *
     * Most hand back a string, but a date column can arrive as a DateTime — SQL
     * Server does so with SQLSRV_ATTR_FETCHES_DATETIME_TYPE — and casting one to
     * a string throws rather than producing a date.
     */
    protected function dayKey(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
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

        $expression = $this->dateExpression();

        $activities = Activity::select(
                DB::raw("{$expression} as date"),
                DB::raw('count(*) as count'),
                'event'
            );

        // Apply date range and other filters
        $this->applyFilters($activities, $filters);

        $activities = $activities->groupBy(DB::raw($expression), 'event')
            ->orderBy('date')
            ->get();

        // Generate all dates in range
        $dates = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        // Indexed once, then read by key.
        //
        // This used to call $activities->where(...)->where(...)->first() for
        // every event type on every day, and each of those scans the whole
        // collection twice and allocates two more. At 90 days and 14 event
        // types over 200k activities that is roughly 3.2 million closure calls:
        // measured at 123 seconds, which is a 500 rather than a chart. The
        // query underneath it takes 271ms.
        $counts = [];

        foreach ($activities as $row) {
            $counts[$this->dayKey($row->date)][$row->event] = (int) $row->count;
        }

        // Only the busiest handful get their own line. An application can log
        // any number of event names — this dataset has thirteen — and past about
        // six the palette starts repeating, so two lines share a colour and the
        // legend stops identifying anything. The rest are summed into one
        // series, which keeps the totals honest.
        $totals = [];

        foreach ($activities as $row) {
            if ($row->event === null || $row->event === '') {
                continue;
            }

            $totals[$row->event] = ($totals[$row->event] ?? 0) + (int) $row->count;
        }

        arsort($totals);
        $limit = (int) config('activitylog-ui.analytics.max_chart_series', 6);
        $eventTypes = collect(array_slice(array_keys($totals), 0, $limit));
        $remainder = array_slice(array_keys($totals), $limit);
        $chartData = [];

        foreach ($eventTypes as $eventType) {
            $eventData = [];
            foreach ($dates as $date) {
                $eventData[] = [
                    'date' => $date,
                    'count' => $counts[$date][$eventType] ?? 0,
                ];
            }

            $colors = config('activitylog-ui.analytics.chart_colors', []);
            $chartData[] = [
                // Snake_case is how applications log; it is not how a chart
                // legend should read.
                'label' => ucfirst(str_replace('_', ' ', (string) $eventType)),
                'data' => $eventData,
                'color' => $colors[$eventType] ?? '#6b7280',
            ];
        }

        // Everything past the limit is summed rather than dropped. A chart that
        // silently omits seven of thirteen event types is worse than one whose
        // colours repeat.
        if ($remainder !== []) {
            $otherData = [];

            foreach ($dates as $date) {
                $sum = 0;

                foreach ($remainder as $eventType) {
                    $sum += $counts[$date][$eventType] ?? 0;
                }

                $otherData[] = ['date' => $date, 'count' => $sum];
            }

            $chartData[] = [
                'label' => sprintf('Other (%d more)', count($remainder)),
                'data' => $otherData,
                'color' => '#6b7280',
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
    public function getUserActivityProfile(int|string $userId, string $userType): array
    {
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . '.' . self::ANALYTICS_CACHE_VERSION . '.' . Activity::sourceFingerprint() . ".user_profile.{$userType}.{$userId}";

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget($cacheKey);
        }

        // Counted in the database rather than in PHP. Loading every activity a
        // causer ever recorded — with its subject morphed in — to produce six
        // summary numbers took eight seconds for a causer with 3,342 of them,
        // and there is no upper bound on how many a busy one has.
        $scope = fn () => Activity::where('causer_type', $userType)->where('causer_id', $userId);

        $total = $scope()->count();
        $span = $scope()->selectRaw('MIN(created_at) as first_at, MAX(created_at) as last_at')->first();

        // Ten rows, so the eager loads the list actually needs are affordable here
        // and nowhere else in this method.
        $recent = $scope()
            ->with(['causer', 'subject'])
            ->orderByDesc('created_at')
            ->orderByDesc((new Activity)->getKeyName())
            ->limit(10)
            ->get();

        // Everything stored here is reduced to plain arrays and scalars. This used
        // to cache Eloquent models and Collections; if that payload could not be
        // unserialized the method still satisfied its `array` return type, so it
        // failed silently with junk data rather than loudly.
        $profile = [
            'total_activities' => $total,
            'first_activity' => $span?->first_at ? Carbon::parse($span->first_at)->toISOString() : null,
            'last_activity' => $span?->last_at ? Carbon::parse($span->last_at)->toISOString() : null,
            'event_breakdown' => $this->getUserEventBreakdown($scope(), $total)->all(),
            'subject_breakdown' => $this->getUserSubjectBreakdown($scope(), $total)->all(),
            'daily_activity' => $this->getUserDailyActivity($scope()),
            'recent_activities' => $recent->toArray(),
        ];

        Cache::put($cacheKey, $profile, 1800);

        return $profile;
    }

    /**
     * Get user's event type breakdown.
     */
    protected function getUserEventBreakdown(Builder $activities, int $total): Collection
    {
        return $activities->selectRaw('event, COUNT(*) as tally')
            ->groupBy('event')
            ->pluck('tally', 'event')
            ->map(fn (int $count, $event) => [
                'event' => $event,
                'label' => ucfirst((string) $event),
                'count' => $count,
                'percentage' => $this->shareOf($count, $total),
            ])
            ->values();
    }

    /**
     * Get user's subject type breakdown.
     */
    protected function getUserSubjectBreakdown(Builder $activities, int $total): Collection
    {
        return $activities->selectRaw('subject_type, COUNT(*) as tally')
            ->groupBy('subject_type')
            ->pluck('tally', 'subject_type')
            ->map(fn (int $count, $subjectType) => [
                'type' => $subjectType,
                'name' => class_basename($subjectType ?: 'Unknown'),
                'count' => $count,
                'percentage' => $this->shareOf($count, $total),
            ])
            ->sortByDesc('count')
            ->values();
    }

    /**
     * A count as a percentage of the whole, without dividing by a zero total.
     */
    protected function shareOf(int $count, int $total): float
    {
        return $total > 0 ? round(($count / $total) * 100, 1) : 0.0;
    }

    /**
     * Get user's daily activity for the last 30 days.
     */
    protected function getUserDailyActivity(Builder $activities): array
    {
        $from = now()->subDays(29)->startOfDay();

        // One grouped query over the window, not one pass over the causer's
        // entire history per day. The previous form walked every activity thirty
        // times to count the handful that fell in the last month.
        $counts = $activities
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as tally')
            ->groupBy('day')
            ->pluck('tally', 'day');

        // Still every day in the window, including the empty ones: the chart
        // draws a continuous month and a gap is not the same as a zero.
        return collect(range(29, 0))
            ->map(function (int $daysAgo) use ($counts) {
                $date = now()->subDays($daysAgo)->toDateString();

                return [
                    'date' => $date,
                    'count' => (int) ($counts[$date] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Get activity heatmap data.
     */
    public function getActivityHeatmap(int $days = 365): array
    {
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . '.' . self::ANALYTICS_CACHE_VERSION . '.' . Activity::sourceFingerprint() . ".heatmap.{$days}";

        return Cache::remember($cacheKey, 3600, function () use ($days) {
            $startDate = now()->subDays($days)->startOfDay();

            $expression = $this->dateExpression();

            $activities = Activity::select(
                    DB::raw("{$expression} as date"),
                    DB::raw('count(*) as count')
                )
                ->where('created_at', '>=', $startDate)
                ->groupBy(DB::raw($expression))
                ->orderBy('date')
                ->get()
                ->keyBy(fn ($row) => $this->dayKey($row->date));

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
        // The one grouped-by-day query the driver-aware expression had not
        // reached, so this was still a syntax error on PostgreSQL and SQL Server.
        $expression = $this->dateExpression();

        $dailyActivity = Activity::select(
                DB::raw("{$expression} as date"),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy(DB::raw($expression))
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($row) => [$this->dayKey($row->date) => (int) $row->count]);

        // No activity in the window means no anomalies, not a crash: avg() is
        // null on an empty collection and the float-typed parameter below then
        // raised a TypeError, so asking for anomalies over a quiet period 500'd.
        if ($dailyActivity->isEmpty()) {
            return [];
        }

        $mean = (float) $dailyActivity->avg();
        $stdDev = $this->calculateStandardDeviation($dailyActivity->values()->toArray(), $mean);

        // Every day identical — including a single day, where the deviation of
        // one value is zero. Nothing stands out, and dividing by it would not.
        if ($stdDev <= 0.0) {
            return [];
        }

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
