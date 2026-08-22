<?php

namespace MuhammadSadeeq\ActivitylogUi\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MuhammadSadeeq\ActivitylogUi\Models\Activity;

class ActivitylogService
{
    /**
     * Get filtered activities with pagination.
     *
     * $anchorId pins the result set to the rows that existed when the first page
     * was read. Offsets are counted from the top of a list ordered newest-first,
     * so on an audit log — which is written to continuously, by definition — every
     * activity recorded between two page requests pushed the whole list down and
     * the next page repeated rows the user had already seen. Ten new rows while
     * reading page 1 meant page 2 opened with the last ten rows of page 1.
     */
    public function getActivities(array $filters = [], int $perPage = 25, ?array $anchor = null): LengthAwarePaginator
    {
        $model = new Activity;

        $query = Activity::query()
            ->with(config('activitylog-ui.performance.eager_load_relations', ['causer', 'subject']))
            // By time first, then by key as a tiebreak.
            //
            // Ordering by the key alone assumed the key rises with created_at.
            // It usually does, but a backdated or imported activity breaks it —
            // and the day headings are derived from created_at, so the list
            // rendered "20 January 2025" above "27 June 2025" on the very first
            // page of the test data. The key still decides ties, which keeps
            // pagination stable for rows sharing a timestamp.
            ->orderByDesc($model->qualifyColumn('created_at'))
            ->orderByDesc($model->getQualifiedKeyName());

        $query = $this->applyFilters($query, $filters);

        $this->applyAnchor($query, $model, $anchor);

        return $query->paginate($perPage);
    }

    /**
     * Pin later pages to the rows that existed when the first one was read.
     *
     * The anchor has to be a prefix of the ordering, which means it has to be
     * the same pair the query sorts by. An earlier version filtered on the key
     * alone while the list was ordered by created_at, and the two disagree the
     * moment anything is backdated or imported: on the reference dataset the
     * newest row by time sat at key 160,488 while page one also held key
     * 203,493, so attaching the anchor cut 44,523 activities — a fifth of the
     * log — out of every page after the first, with the footer still reporting
     * the full count.
     *
     * The pair is compared rather than the key alone, so this no longer asks
     * whether the key increments. That is a large improvement for random UUID
     * and ULID keys and not a complete fix: within a single timestamp the key is
     * still the tiebreak, so a row inserted at the anchor's exact time with a
     * lower random key does join the frozen set. The window is one timestamp
     * wide instead of the whole listing, and narrower still because the anchor
     * carries microseconds, but it is not zero.
     *
     * @param  array{time: string, id: int|string}|null  $anchor
     */
    protected function applyAnchor(Builder $query, Activity $model, ?array $anchor): void
    {
        if ($anchor === null) {
            return;
        }

        $createdAt = $model->qualifyColumn('created_at');
        $key = $model->getQualifiedKeyName();

        $query->where(function (Builder $outer) use ($createdAt, $key, $anchor) {
            $outer
                ->where($createdAt, '<', $anchor['time'])
                ->orWhere(function (Builder $tie) use ($createdAt, $key, $anchor) {
                    $tie->where($createdAt, '=', $anchor['time'])
                        ->where($key, '<=', $anchor['id']);
                });
        });
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
        //
        // No re-casting of the id here: sanitizeId() already chose int or string,
        // and is_numeric() would turn a 26-digit ULID into PHP_INT_MAX.
        $causerType = $this->presentFilter($filters, 'causer_type');
        $causerId = $this->presentFilter($filters, 'causer_id');

        if ($causerType !== null || $causerId !== null) {
            $query->byCauser($causerType, $causerId);
        }

        // Subject filters
        $subjectType = $this->presentFilter($filters, 'subject_type');
        $subjectId = $this->presentFilter($filters, 'subject_id');

        if ($subjectType !== null || $subjectId !== null) {
            $query->bySubject($subjectType, $subjectId);
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
     * A filter value the caller actually supplied, or null.
     *
     * empty() cannot be used for this: it calls '0' absent, so a causer or
     * subject whose key really is 0 — legal in MySQL, and present in plenty of
     * migrated data — passed validation in the controller and was then dropped
     * here without a word, listing the whole log as though no filter had been
     * asked for.
     */
    protected function presentFilter(array $filters, string $key): int|string|null
    {
        $value = $filters[$key] ?? null;

        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }

        return is_int($value) ? $value : (string) $value;
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
     * Version suffix for the filter-option cache keys.
     *
     * Bump this whenever the cached row shape OR its semantics change, so an
     * upgrade cannot serve a payload written by an older release. v3 deduplicated
     * causers by type and id; v4 stopped letting an email reach the display name
     * when filters.expose_causer_email is off, so a v3 entry can still be
     * publishing addresses the flag is meant to withhold. v5 dropped the
     * generated Tailwind class strings from the event types.
     */
    protected const FILTER_CACHE_VERSION = 'v5';

    /**
     * Names of the filter-option caches, for invalidation.
     */
    protected const FILTER_CACHES = ['causers', 'subject_types', 'event_types', 'event_types_with_styling'];

    /**
     * Read a cached list of rows, recomputing when the stored value is not the
     * plain array of arrays that was written.
     *
     * These caches used to hold Collection objects. If such a payload cannot be
     * unserialized on read — a class the reading process cannot load, a store
     * shared with another application — PHP hands back __PHP_Incomplete_Class,
     * which then violated the declared Collection return type and took the whole
     * dashboard down with an unhandled TypeError (issue #12). Storing plain
     * arrays removes the class dependency, and validating on read means anything
     * unexpected is discarded and recomputed rather than returned.
     *
     * @param  callable(): array<int, array<string, mixed>>  $compute
     * @return Collection<int, array<string, mixed>>
     */
    protected function rememberFilterOptions(string $name, callable $compute): Collection
    {
        $key = $this->filterCacheKey($name);
        $cached = $this->readFilterOptions($name, $key);

        if ($cached !== null) {
            return collect($cached);
        }

        // Single-flight from here. The causer list is a DISTINCT over the whole
        // activity table with the causers eager-loaded behind it, so when the TTL
        // expires under load every request in flight used to run that scan at
        // once — the slower it is, the more of them pile onto it. One computes;
        // the rest wait briefly and read what it wrote.
        $lock = $this->cacheLock($key);

        if ($lock === null) {
            return collect($this->writeFilterOptions($key, $compute()));
        }

        try {
            $lock->block($this->lockWaitSeconds());
        } catch (LockTimeoutException $e) {
            // Whoever holds it is still working. One more read first: the common
            // case is that they finished during the wait, and taking their result
            // is the whole point of having waited.
            $cached = $this->readFilterOptions($name, $key);

            return collect($cached ?? $this->writeFilterOptions($key, $compute()));
        } catch (\Exception $e) {
            // Not a timeout — the lock backend itself failed. Reported, because
            // silently degrading to a full scan on every request is exactly the
            // situation this whole mechanism exists to avoid.
            //
            // Exception, not Throwable: every store signals failure with one, so
            // an Error here is a defect in this package rather than an unhealthy
            // backend. Catching those too turned a call to a method that did not
            // exist into a warning nobody read, with the single-flight silently
            // disabled and every request running the scan it was added to avoid.
            $this->reportCacheFailure($key, 'lock', $e);

            return collect($this->writeFilterOptions($key, $compute()));
        }

        try {
            $cached = $this->readFilterOptions($name, $key);

            if ($cached !== null) {
                return collect($cached);
            }

            return collect($this->writeFilterOptions($key, $compute()));
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $e) {
                // Already gone if it outlived its own TTL. Reported all the same:
                // every store checks ownership before releasing, so a failure
                // here means the lock backend is unhealthy rather than merely
                // that someone else took over.
                $this->reportCacheFailure($key, 'unlock', $e);
            }
        }
    }

    /**
     * Read a filter-option cache, returning null when there is nothing usable.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function readFilterOptions(string $name, string $key): ?array
    {
        // Eviction is inside the guard too: a store that dies between the read and
        // the forget would otherwise throw straight out of the service.
        try {
            $cached = Cache::get($key);

            if ($this->isValidRows($name, $cached)) {
                return $cached;
            }

            if ($cached !== null) {
                Cache::forget($key);
            }
        } catch (\Throwable $e) {
            $this->reportCacheFailure($key, 'read', $e);
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function writeFilterOptions(string $key, array $rows): array
    {
        try {
            Cache::put($key, $rows, config('activitylog-ui.performance.cache_ttl', 3600));
        } catch (\Throwable $e) {
            $this->reportCacheFailure($key, 'write', $e);
        }

        return $rows;
    }

    /**
     * A lock for the recompute of one cache key, or null when the configured
     * store cannot provide one.
     *
     * Most shipped stores are lock providers, but what they coordinate differs:
     * the array driver locks within one PHP process only, and the null driver's
     * lock always succeeds. Neither serialises anything across requests, which
     * is correct — with no cache there is nothing to share — but it does mean a
     * lock acquired is not by itself proof of exclusivity. A store that is not a
     * provider at all degrades to the previous behaviour rather than failing.
     */
    protected function cacheLock(string $key): ?Lock
    {
        try {
            if (! Cache::getStore() instanceof LockProvider) {
                return null;
            }

            // Long enough for the scan this guards, short enough that a worker
            // killed mid-compute does not park every other request behind a lock
            // nobody will ever release. Both bounds are configurable, and were
            // documented as such before anything read them.
            return Cache::lock($key . ':recompute', $this->lockTtlSeconds());
        } catch (\Exception $e) {
            $this->reportCacheFailure($key, 'lock', $e);

            return null;
        }
    }

    /**
     * How long a request waits for whoever is already recomputing.
     */
    protected function lockWaitSeconds(): int
    {
        return max(0, (int) config('activitylog-ui.performance.filter_lock_wait', 3));
    }

    /**
     * How long that recompute may hold the lock before it is presumed dead.
     */
    protected function lockTtlSeconds(): int
    {
        return max(1, (int) config('activitylog-ui.performance.filter_lock_ttl', 30));
    }

    /**
     * Report a cache failure without failing the request.
     *
     * Degrading to a recomputed value is correct, but doing it silently means a
     * permanently broken cache store turns into permanent full-table scans with
     * no signal at all. Logged once per key and operation per request.
     *
     * @var array<string, true>
     */
    protected array $reportedCacheFailures = [];

    protected function reportCacheFailure(string $key, string $operation, \Throwable $e): void
    {
        if (isset($this->reportedCacheFailures["{$key}:{$operation}"])) {
            return;
        }

        $this->reportedCacheFailures["{$key}:{$operation}"] = true;

        Log::warning("Activity log UI cache {$operation} failed; falling back to a live query.", [
            'key' => $key,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Build a versioned cache key for a filter-option list.
     */
    protected function filterCacheKey(string $name): string
    {
        // Scoped to the source: the activity table and connection are resolved
        // from the host's configured model, so pointing the UI somewhere else must
        // not serve the previous table's causers and event types. Across tenants
        // that is a disclosure, not merely stale data.
        //
        // Scoped to the display settings too, so toggling expose_causer_email or
        // changing causer_name_attributes takes effect immediately rather than
        // after the TTL — the causer list embeds the resolved display name.
        return config('activitylog-ui.performance.cache_prefix')
            . '.' . self::FILTER_CACHE_VERSION
            . '.' . Activity::sourceFingerprint()
            . '.' . $this->displayFingerprint()
            . '.' . $name;
    }

    /**
     * Fingerprint of the settings that shape a cached causer's display name.
     */
    protected function displayFingerprint(): string
    {
        return substr(sha1(json_encode([
            config('activitylog-ui.ui.causer_name_attributes', ['name', 'email']),
            (bool) config('activitylog-ui.filters.expose_causer_email', false),
        ])), 0, 8);
    }

    /**
     * Keys each filter-option cache is required to carry, so a payload written
     * for one cache cannot be served from another and a half-written row is
     * rejected rather than rendered blank.
     */
    protected const FILTER_CACHE_KEYS = [
        'causers' => ['id', 'type', 'name', 'label'],
        'subject_types' => ['value', 'label', 'full_name'],
        'event_types' => ['value', 'label'],
        'event_types_with_styling' => ['value', 'label', 'icon'],
    ];

    /**
     * Whether a cached value is exactly what this particular cache writes.
     *
     * "A list of arrays" is not enough. It would accept event rows served from
     * the causers key, an associative array that JSON-encodes to an object the
     * frontend cannot map over, and — the case this all exists for — a row whose
     * nested value is an unresolvable object.
     */
    protected function isValidRows(string $name, mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        $required = self::FILTER_CACHE_KEYS[$name] ?? [];

        foreach ($value as $row) {
            if (!is_array($row)) {
                return false;
            }

            foreach ($required as $key) {
                if (!array_key_exists($key, $row)) {
                    return false;
                }
            }

            if (!$this->isPlainData($row)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a value is made only of scalars, nulls and arrays of the same.
     *
     * Any object fails, including __PHP_Incomplete_Class, at any depth.
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
     * Discard every filter-option cache. Call after bulk-importing or pruning
     * activities so the dropdowns do not lag behind by up to the cache TTL.
     */
    public function flushFilterOptions(): void
    {
        foreach (self::FILTER_CACHES as $name) {
            Cache::forget($this->filterCacheKey($name));
        }
    }

    /**
     * Get available causers for filtering.
     */
    public function getAvailableCausers(): Collection
    {
        // When email exposure is off, it must not sneak back in through the display
        // name: causer_name falls back to email, so a causer without a name would
        // otherwise publish the very address the flag withholds.
        $displayAttributes = (array) config('activitylog-ui.ui.causer_name_attributes', ['name', 'email']);

        if (!config('activitylog-ui.filters.expose_causer_email', false)) {
            $displayAttributes = array_values(array_diff($displayAttributes, ['email']));
        }

        return $this->rememberFilterOptions('causers', function () use ($displayAttributes) {
            return Activity::select('causer_type', 'causer_id')
                ->whereNotNull('causer_type')
                ->whereNotNull('causer_id')
                ->with('causer')
                ->distinct()
                ->get()
                ->filter(function ($activity) {
                    return $activity->causer !== null;
                })
                ->map(function ($activity) use ($displayAttributes) {
                    $name = $activity->causerNameUsing($displayAttributes);

                    return [
                        'id' => $activity->causer_id,
                        'type' => $activity->causer_type,
                        'name' => $name,
                        'email' => $this->causerEmail($activity),
                        'label' => $name . ' (' . class_basename($activity->causer_type) . ')',
                    ];
                })
                // Keyed by type AND id: causers are polymorphic, so App\Models\User#7
                // and App\Models\Admin#7 are different people. Deduplicating on id
                // alone dropped one of them from the dropdown entirely.
                ->unique(fn (array $causer) => $causer['type'] . '#' . $causer['id'])
                ->values()
                ->all();
        });
    }

    /**
     * Resolve a causer's email address for the filter dropdown.
     *
     * Reading the attribute can run a host accessor or an encrypted cast, either
     * of which may throw. An optional display field must never take the whole
     * filter panel down, so failures resolve to null.
     */
    protected function causerEmail(Activity $activity): ?string
    {
        if (!config('activitylog-ui.filters.expose_causer_email', false)) {
            return null;
        }

        try {
            $email = $activity->causer->email ?? null;
        } catch (\Throwable) {
            return null;
        }

        return is_scalar($email) ? (string) $email : null;
    }

    /**
     * Get available subject types for filtering.
     */
    public function getAvailableSubjectTypes(): Collection
    {
        return $this->rememberFilterOptions('subject_types', function () {
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
                ->values()
                ->all();
        });
    }

    /**
     * Get available event types for filtering.
     */
    public function getAvailableEventTypes(): Collection
    {
        return $this->rememberFilterOptions('event_types', function () {
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
                ->values()
                ->all();
        });
    }

    /**
     * Get available event types with styling information for UI components.
     */
    public function getEventTypesWithStyling(): Collection
    {
        return $this->rememberFilterOptions('event_types_with_styling', function () {
            $eventTypes = Activity::select('event')
                ->whereNotNull('event')
                ->distinct()
                ->pluck('event')
                ->values();

            // The colour, gradient and badge/timeline class strings that used
            // to be here described Tailwind utilities for a stylesheet the
            // package no longer ships, and they were the bulk of this payload —
            // roughly 500 bytes per event type, sent on every dashboard load and
            // used by nothing. The interface derives an event's appearance from
            // its name; what the server has to say is the name itself.
            return $eventTypes->map(function ($event) {
                return [
                    'value' => $event,
                    'label' => ucfirst(str_replace('_', ' ', $event)),
                    'icon' => $this->selectIconForEventType($event),
                ];
            })->all();
        });
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
     * Get recent activities for real-time updates.
     */
    public function getRecentActivities(int $hours = 1, int $limit = 50): Collection
    {
        return Activity::with(['causer', 'subject'])
            ->recent($hours)
            ->limit($limit)
            ->latest('id')
            ->get();
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
    public function saveView(array $filters, string $name, string|int|null $userId = null): array
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
    public function getSavedViews(string|int|null $userId = null): array
    {
        $cacheKey = config('activitylog-ui.performance.cache_prefix') . ".saved_views.{$userId}";
        return Cache::get($cacheKey, []);
    }

    /**
     * Delete a saved view.
     */
    public function deleteSavedView(string $viewId, string|int|null $userId = null): bool
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
     *
     * Every limit here is in the query rather than applied to the result. The
     * previous form hydrated the entire activity table with its causers to find
     * five names, and pulled every distinct matching description into memory
     * before taking five of those — at 200,000 activities the endpoint ran out
     * of memory rather than answering.
     */
    public function getSearchSuggestions(string $query): Collection
    {
        if (strlen($query) < 2) {
            return collect();
        }

        return collect()
            ->concat($this->causerSuggestions($query))
            ->concat($this->columnSuggestions('description', $query, 5, fn (string $value) => [
                'value' => $value,
                'label' => $value,
                'type' => 'Description',
            ]))
            ->concat($this->columnSuggestions('subject_type', $query, 3, fn (string $value) => [
                'value' => $value,
                'label' => class_basename($value),
                'type' => 'Model',
            ]))
            ->take(10)
            ->values();
    }

    /**
     * Causer suggestions, drawn from the same list the filter dropdown uses.
     *
     * Which means they are already deduplicated by type and id, already cached,
     * and already resolved through the configured display attributes — so this
     * cannot publish an email that filters.expose_causer_email withholds, which
     * the previous form did in both the value it matched on and the label it
     * returned.
     *
     * @return Collection<int, array<string, string>>
     */
    protected function causerSuggestions(string $query): Collection
    {
        $exposeEmail = (bool) config('activitylog-ui.filters.expose_causer_email', false);

        return $this->getAvailableCausers()
            ->filter(function (array $causer) use ($query, $exposeEmail) {
                if (stripos((string) $causer['name'], $query) !== false) {
                    return true;
                }

                return $exposeEmail && stripos((string) ($causer['email'] ?? ''), $query) !== false;
            })
            ->take(5)
            ->map(fn (array $causer) => [
                'value' => (string) $causer['name'],
                'label' => (string) $causer['label'],
                'type' => 'User',
            ])
            ->values();
    }

    /**
     * Distinct values of one activity column matching a substring.
     *
     * @param  callable(string): array<string, string>  $format
     * @return Collection<int, array<string, string>>
     */
    protected function columnSuggestions(string $column, string $query, int $limit, callable $format): Collection
    {
        $model = new Activity;
        $grammar = $model->getConnection()->getQueryGrammar();

        return Activity::query()
            ->whereNotNull($column)
            // ESCAPE stated rather than assumed. MySQL and Postgres treat
            // backslash as the escape character by default; SQLite has none
            // unless one is named, so there the escaping would have reached the
            // driver as two literal characters and a search for a literal '%'
            // would have matched nothing.
            //
            // '!' rather than a backslash, because a backslash is itself an
            // escape inside a MySQL string literal and ESCAPE '\' is a syntax
            // error there.
            ->whereRaw(
                $grammar->wrap($model->qualifyColumn($column)) . " LIKE ? ESCAPE '!'",
                ['%' . $this->escapeLike($query) . '%']
            )
            ->distinct()
            ->limit($limit)
            ->pluck($column)
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->map($format)
            ->values();
    }

    /**
     * Neutralise the wildcards LIKE would otherwise read as syntax.
     *
     * Bindings keep this safe either way; what they do not do is stop a typed
     * '%' from matching the whole table and a typed '_' from matching more than
     * the user asked for.
     *
     * Paired with the ESCAPE '!' clause above. The escape character has to be
     * escaped first, or a query containing '!' would consume the '%' after it.
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /**
     * Get related activities for a given subject.
     */
    public function getRelatedActivities(string $subjectType, int|string $subjectId, int|string|null $excludeId = null): Collection
    {
        $query = Activity::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->with(['causer'])
            ->orderBy('id', 'desc')
            ->take(10);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }
}
