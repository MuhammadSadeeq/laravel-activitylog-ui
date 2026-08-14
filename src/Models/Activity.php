<?php

namespace MuhammadSadeeq\ActivitylogUi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use MuhammadSadeeq\ActivitylogUi\Eloquent\MorphTypes;
use MuhammadSadeeq\ActivitylogUi\Eloquent\SafeMorphTo;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    /**
     * The UI renders the causer's display name, which needs the accessor's
     * fallback logic rather than a raw `causer.name` off the relation.
     *
     * @var array<int, string>
     */
    protected $appends = ['causer_name'];

    /**
     * Cached per connection and table, because both are now resolved from the
     * host's configured activity model and can differ between them.
     *
     * @var array<string, bool>
     */
    protected static array $hasAttributeChangesColumn = [];

    /**
     * Guards against a configured model that extends this one re-entering
     * resolution through its own constructor.
     */
    protected static bool $resolvingActivitySource = false;

    /**
     * Spatie v5 dropped activitylog.table_name and database_connection, so a
     * custom model registered as activitylog.activity_model is the only way left
     * to move the log elsewhere. Read where it points and follow it — otherwise
     * the UI reads activity_log while the application writes somewhere else
     * entirely (issue #9).
     *
     * Resolved here rather than in the constructor: these are called a handful of
     * times per query instead of once per hydrated row, and resolving afresh
     * means a model that derives its table from request or tenant context is
     * followed rather than frozen at whatever it returned first.
     */
    public function getTable()
    {
        return static::configuredActivitySource()['table'] ?? parent::getTable();
    }

    public function getConnectionName()
    {
        $source = static::configuredActivitySource();

        return $source === null ? parent::getConnectionName() : $source['connection'];
    }

    /**
     * @return array{table: string, connection: string|null}|null
     */
    protected static function configuredActivitySource(): ?array
    {
        if (static::$resolvingActivitySource) {
            return null;
        }

        $class = config('activitylog.activity_model');

        if (!is_string($class) || $class === '' || $class === static::class) {
            return null;
        }

        static::$resolvingActivitySource = true;

        try {
            if (!class_exists($class)) {
                throw new \RuntimeException(
                    "activitylog.activity_model is set to [{$class}], which does not exist. "
                    . 'Fix the configured model rather than letting the UI read a different activity log.'
                );
            }

            // Spatie requires both, and so must this: accepting any Eloquent model
            // would let a misconfiguration point the UI at, say, the users table
            // and serialise password hashes into the activity list.
            if (!is_a($class, Model::class, true) || !is_a($class, ActivityContract::class, true)) {
                throw new \RuntimeException(
                    "activitylog.activity_model is set to [{$class}], which is not an Eloquent model implementing "
                    . ActivityContract::class . '. Pointing the UI at an arbitrary model would read that table and '
                    . 'serialise its rows as activities.'
                );
            }

            $instance = new $class;

            return [
                'table' => $instance->getTable(),
                'connection' => $instance->getConnectionName(),
            ];
        } catch (\Throwable $e) {
            // Fail closed. Falling back to the default table here would quietly
            // show whichever log the default connection holds — in a multi-tenant
            // application, another tenant's audit trail. A configured model that
            // cannot be resolved is a configuration fault, and reading the wrong
            // records is a worse answer than refusing to read any.
            throw new \RuntimeException(
                "activitylog.activity_model is set to [{$class}], which could not be resolved: {$e->getMessage()}. "
                . 'Fix the configured model rather than letting the UI read a different activity log.',
                previous: $e
            );
        } finally {
            static::$resolvingActivitySource = false;
        }
    }


    /**
     * A stable fingerprint of where activities are being read from.
     *
     * Cache keys derived from activity data include this, so pointing the UI at a
     * different table or connection cannot serve the previous source's causers,
     * counts or analytics — which across tenants would be a disclosure, not just
     * a staleness bug.
     */
    public static function sourceFingerprint(): string
    {
        $model = new static();

        return substr(sha1(($model->getConnectionName() ?? 'default') . '|' . $model->getTable()), 0, 12);
    }

    /**
     * Forget resolved schema state. Intended for tests and long-lived workers.
     */
    public static function flushConfiguredActivitySource(): void
    {
        static::$hasAttributeChangesColumn = [];
    }

    public static function hasAttributeChangesColumn(): bool
    {
        $model = new static();
        $connection = $model->getConnectionName();
        $key = ($connection ?? 'default') . '.' . $model->getTable();

        return static::$hasAttributeChangesColumn[$key] ??= Schema::connection($connection)
            ->hasColumn($model->getTable(), 'attribute_changes');
    }

    /**
     * Get the causer (user who performed the activity).
     */
    public function causer(): MorphTo
    {
        // No withDefault(): on a morphTo there is no single related class, so
        // Eloquent's default is an instance of THIS model. initRelation() seeds
        // every row with it before matching, so an eager load handed back an
        // Activity posing as the causer — which is why the null-check in
        // getAvailableCausers() never filtered anything, and which recursed
        // without end once causer_name was appended and serialised.
        // Null is the honest answer; the accessors fall back to type and id.
        return $this->morphTo()->withoutGlobalScopes();
    }

    /**
     * Get the subject (model that was acted upon).
     */
    public function subject(): MorphTo
    {
        $relation = $this->morphTo();

        // Spatie's model drops the soft-delete scope when this is enabled; the
        // override has to keep doing that or soft-deleted subjects silently
        // disappear from the log.
        if (config('activitylog.include_soft_deleted_subjects')) {
            $relation->withoutGlobalScope(SoftDeletingScope::class);
        }

        // See causer(): withDefault() on a morphTo yields an instance of this
        // model, which is never the right answer.
        return $relation;
    }


    /**
     * Use a MorphTo that tolerates recorded types whose class is gone.
     *
     * Covers the eager-loading path.
     */
    protected function newMorphTo(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation)
    {
        return new SafeMorphTo($query, $parent, $foreignKey, $ownerKey, $type, $relation);
    }

    /**
     * Covers the lazy path, where Eloquent instantiates the target class before
     * the relation object even exists.
     *
     * Rather than instantiating a class that is no longer there, relate to
     * nothing: the query cannot match and the default model is switched off, so
     * the relation reads as null instead of throwing.
     */
    protected function morphInstanceTo($target, $name, $type, $id, $ownerKey)
    {
        if (MorphTypes::missing($target)) {
            // Flagged rather than constrained: SafeMorphTo::getResults() then
            // answers null outright. A never-matching query would still be sent,
            // once per row — 782 pointless statements on the test dataset.
            return $this->newMorphTo(
                $this->newQuery(),
                $this,
                $id,
                $ownerKey ?? $this->getKeyName(),
                $type,
                $name
            )->markTypeAsMissing()->withDefault(false);
        }

        return parent::morphInstanceTo($target, $name, $type, $id, $ownerKey);
    }

    /**
     * Scope for filtering by date range.
     */
    public function scopeDateRange(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope for filtering by date preset.
     */
    public function scopeDatePreset(Builder $query, ?string $preset): Builder
    {
        if (!$preset) {
            return $query;
        }

        $now = Carbon::now();

        // Carbon is mutable, so every branch works on a copy. `last_month` in
        // particular used to call subMonth() twice, taking its month and its year
        // from two different points in time, and subMonth() on the 31st overflows
        // into the following month rather than clamping.
        $lastMonth = $now->copy()->subMonthNoOverflow();

        return match ($preset) {
            'today' => $query->whereDate('created_at', $now->toDateString()),
            'yesterday' => $query->whereDate('created_at', $now->copy()->subDay()->toDateString()),
            'last_7_days' => $query->where('created_at', '>=', $now->copy()->subDays(7)),
            'last_30_days' => $query->where('created_at', '>=', $now->copy()->subDays(30)),
            'this_month' => $query->whereMonth('created_at', $now->month)
                                 ->whereYear('created_at', $now->year),
            'last_month' => $query->whereMonth('created_at', $lastMonth->month)
                                 ->whereYear('created_at', $lastMonth->year),
            default => $query,
        };
    }

    /**
     * Scope for filtering by causer.
     */
    public function scopeByCauser(Builder $query, ?string $causerType = null, mixed $causerId = null): Builder
    {
        if ($causerType) {
            $query->where('causer_type', $causerType);
        }

        if ($causerId !== null && $causerId !== '') {
            // Bound as given. Casting numeric strings here overflowed long
            // all-digit keys, such as a 26-character ULID, to PHP_INT_MAX.
            $query->where('causer_id', $causerId);
        }

        return $query;
    }

    /**
     * Scope for filtering by subject.
     */
    public function scopeBySubject(Builder $query, ?string $subjectType = null, mixed $subjectId = null): Builder
    {
        if ($subjectType) {
            $query->where('subject_type', $subjectType);
        }

        if ($subjectId !== null && $subjectId !== '') {
            $query->where('subject_id', $subjectId);
        }

        return $query;
    }

    /**
     * Scope for searching across multiple fields.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('description', 'like', "%{$search}%")
              ->orWhere('properties', 'like', "%{$search}%");

            if (static::hasAttributeChangesColumn()) {
                $q->orWhere('attribute_changes', 'like', "%{$search}%");
            }

            // Restricted to types that still resolve. A wildcard whereHas makes
            // Eloquent enumerate every distinct causer_type and instantiate each
            // one, so a single deleted class threw — and since analytics now
            // shares this filtering, that took the whole dashboard with it.
            $types = static::searchableCauserTypes();

            if ($types !== []) {
                $q->orWhereHasMorph('causer', $types, function (Builder $causerQuery, string $type) use ($search) {
                    $columns = static::searchableCauserColumns($causerQuery->getModel());

                    if ($columns === []) {
                        // Nothing to match on; make this type contribute nothing
                        // rather than referencing a column it does not have.
                        $causerQuery->whereRaw('1 = 0');

                        return;
                    }

                    $causerQuery->where(function (Builder $inner) use ($columns, $search) {
                        foreach ($columns as $column) {
                            $inner->orWhere($column, 'like', "%{$search}%");
                        }
                    });
                });
            }
        });
    }

    /**
     * Distinct causer types recorded in the log whose class still resolves.
     *
     * @return array<int, string>
     */
    protected static function searchableCauserTypes(): array
    {
        return static::query()
            ->newQuery()
            ->distinct()
            ->whereNotNull('causer_type')
            ->pluck('causer_type')
            ->filter(fn ($type) => !MorphTypes::missing($type))
            ->values()
            ->all();
    }

    /**
     * Which of the configured display attributes are real columns on a causer.
     *
     * The previous form assumed every causer table had both `name` and `email`;
     * a model keyed on something else produced an unknown-column error.
     *
     * @return array<int, string>
     */
    protected static function searchableCauserColumns(Model $causer): array
    {
        $candidates = (array) config('activitylog-ui.ui.causer_name_attributes', ['name', 'email']);
        $key = ($causer->getConnectionName() ?? 'default') . '.' . $causer->getTable();

        static::$searchableColumns[$key] ??= array_values(array_filter(
            $candidates,
            fn ($column) => is_string($column) && Schema::connection($causer->getConnectionName())
                ->hasColumn($causer->getTable(), $column)
        ));

        return static::$searchableColumns[$key];
    }

    /** @var array<string, array<int, string>> */
    protected static array $searchableColumns = [];

    /**
     * Scope for filtering by event types.
     */
    public function scopeByEventTypes(Builder $query, array $eventTypes): Builder
    {
        if (empty($eventTypes)) {
            return $query;
        }

        return $query->whereIn('event', $eventTypes);
    }

    /**
     * Scope for recent activities.
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', Carbon::now()->subHours($hours));
    }

    /**
     * Get the event type with proper formatting.
     */
    public function getEventTypeAttribute(): string
    {
        return ucfirst($this->event ?? 'unknown');
    }

    /**
     * Get formatted changes for display.
     *
     * Handles all event shapes: created (attributes only), deleted (old only),
     * and updated (both old and attributes). Falls back to legacy properties
     * for rows not yet migrated to the attribute_changes column.
     */
    public function getFormattedChangesAttribute(): array
    {
        $data = $this->attribute_changes;

        if ($data === null) {
            $properties = $this->properties;

            if ($properties === null) {
                return [];
            }

            if (!isset($properties['old']) && !isset($properties['attributes'])) {
                return [];
            }

            $data = $properties;
        }

        $changes = [];
        $old = $data['old'] ?? [];
        $new = $data['attributes'] ?? [];
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            $changes[] = [
                'field' => $key,
                'old' => $old[$key] ?? null,
                'new' => $new[$key] ?? null,
            ];
        }

        return $changes;
    }

    /**
     * Get causer name with fallback.
     */
    public function getCauserNameAttribute(): string
    {
        if (!$this->causer) {
            // A recorded type whose class is gone reads as no causer at all, so
            // name it from the log rather than calling it a system action.
            return $this->causer_type
                ? class_basename($this->causer_type) . " #{$this->causer_id}"
                : 'System';
        }

        // Which attributes to try is configurable: not every application keys its
        // users on `name`, and hardcoding it made those causers show as "Unknown".
        return $this->causerNameUsing(
            (array) config('activitylog-ui.ui.causer_name_attributes', ['name', 'email'])
        );
    }

    /**
     * Resolve the causer's display name from a specific list of attributes.
     *
     * Separate from the accessor so callers that must not fall back to certain
     * attributes — the filter options endpoint, which honours
     * filters.expose_causer_email — can narrow the list.
     *
     * @param  array<int, string>  $attributes
     */
    public function causerNameUsing(array $attributes): string
    {
        if (!$this->causer) {
            return $this->causer_type
                ? class_basename($this->causer_type) . " #{$this->causer_id}"
                : 'System';
        }

        foreach ($attributes as $attribute) {
            try {
                $value = $this->causer->{$attribute} ?? null;
            } catch (\Throwable $e) {
                // A host accessor or cast may throw. Carry on to the next candidate
                // rather than failing the page, but say so: silently changing which
                // identity is displayed is not something to do quietly.
                Log::warning('Activity log UI could not read a causer display attribute.', [
                    'causer_type' => $this->causer_type,
                    'attribute' => $attribute,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return $this->causer_type
            ? class_basename($this->causer_type) . " #{$this->causer_id}"
            : 'Unknown User';
    }

    /**
     * Get subject name with fallback.
     */
    public function getSubjectNameAttribute(): string
    {
        if (!$this->subject) {
            // Same here: the row still records which type and id it referred to,
            // which is more use than "Unknown".
            return $this->subject_type
                ? class_basename($this->subject_type) . " #{$this->subject_id}"
                : 'Unknown';
        }

        return $this->subject->name ??
               $this->subject->title ??
               class_basename($this->subject_type) . " #{$this->subject_id}";
    }

    /**
     * Check if activity has attribute changes.
     *
     * Falls back to legacy properties for rows not yet migrated.
     */
    public function hasAttributeChanges(): bool
    {
        $data = $this->attribute_changes;

        if ($data !== null) {
            return isset($data['old']) || isset($data['attributes']);
        }

        $properties = $this->properties;

        return $properties !== null
            && (isset($properties['old']) || isset($properties['attributes']));
    }

    /** @deprecated Use hasAttributeChanges() instead */
    public function hasPropertyChanges(): bool
    {
        return $this->hasAttributeChanges();
    }

    /**
     * Get summary of changes.
     */
    public function getChangesSummary(): string
    {
        if (!$this->hasAttributeChanges()) {
            return 'No changes tracked';
        }

        $changes = $this->formatted_changes;
        $count = count($changes);

        if ($count === 0) {
            return 'No changes';
        }

        if ($count === 1) {
            return "Changed {$changes[0]['field']}";
        }

        return "Changed {$count} fields";
    }

    /**
     * Get activity icon based on event type.
     */
    public function getIconAttribute(): string
    {
        return match ($this->event) {
            'created' => 'plus-circle',
            'updated' => 'pencil-square',
            'deleted' => 'trash',
            'restored' => 'arrow-path',
            default => 'document-text',
        };
    }

    /**
     * Get activity color based on event type.
     */
    public function getColorAttribute(): string
    {
        return match ($this->event) {
            'created' => 'green',
            'updated' => 'blue',
            'deleted' => 'red',
            'restored' => 'yellow',
            default => 'gray',
        };
    }
}
