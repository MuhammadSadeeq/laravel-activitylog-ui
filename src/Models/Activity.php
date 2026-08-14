<?php

namespace MuhammadSadeeq\ActivitylogUi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use MuhammadSadeeq\ActivitylogUi\Eloquent\MorphTypes;
use MuhammadSadeeq\ActivitylogUi\Eloquent\SafeMorphTo;
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

    protected static ?bool $hasAttributeChangesColumn = null;

    public static function hasAttributeChangesColumn(): bool
    {
        if (static::$hasAttributeChangesColumn === null) {
            $model = new static();

            static::$hasAttributeChangesColumn = Schema::connection($model->getConnectionName())
                ->hasColumn($model->getTable(), 'attribute_changes');
        }

        return static::$hasAttributeChangesColumn;
    }

    /**
     * Get the causer (user who performed the activity).
     */
    public function causer(): MorphTo
    {
        $relation = $this->morphTo()->withoutGlobalScopes();

        return $this->withDefaultUnlessTypeMissing($relation, 'causer_type');
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

        return $this->withDefaultUnlessTypeMissing($relation, 'subject_type');
    }

    /**
     * Apply withDefault() only when the recorded type still resolves.
     *
     * For a type whose class is gone, a default instance would be an empty
     * Activity standing in for the missing record — worse than null, because it
     * serialises into the API response as though a subject were loaded.
     */
    protected function withDefaultUnlessTypeMissing(MorphTo $relation, string $typeColumn): MorphTo
    {
        $type = $this->getAttributeFromArray($typeColumn);

        // No recorded type means there is genuinely no related record, and a type
        // whose class is gone cannot be instantiated. In both cases withDefault()
        // fabricates an instance of THIS model as the causer/subject — which is
        // wrong on its face, and recurses without end once causer_name is appended
        // and serialised.
        if ($type === null || $type === '' || MorphTypes::missing($type)) {
            return $relation;
        }

        return $relation->withDefault();
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
            // Convert to integer if it's a numeric string
            $causerId = is_numeric($causerId) ? (int) $causerId : $causerId;
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
            // Convert to integer if it's a numeric string
            $subjectId = is_numeric($subjectId) ? (int) $subjectId : $subjectId;
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

            $q->orWhereHas('causer', function (Builder $causerQuery) use ($search) {
                  $causerQuery->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
              });
        });
    }

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
        $attributes = config('activitylog-ui.ui.causer_name_attributes', ['name', 'email']);

        foreach ((array) $attributes as $attribute) {
            try {
                $value = $this->causer->{$attribute} ?? null;
            } catch (\Throwable) {
                // A host accessor or cast may throw; try the next candidate.
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
