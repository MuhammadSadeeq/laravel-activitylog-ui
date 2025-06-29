<?php

namespace MuhammadSadeeq\ActivitylogUi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the causer (user who performed the activity).
     */
    public function causer(): MorphTo
    {
        return $this->morphTo()->withDefault();
    }

    /**
     * Get the subject (model that was acted upon).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo()->withDefault();
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

        return match ($preset) {
            'today' => $query->whereDate('created_at', $now->toDateString()),
            'yesterday' => $query->whereDate('created_at', $now->subDay()->toDateString()),
            'last_7_days' => $query->where('created_at', '>=', $now->subDays(7)),
            'last_30_days' => $query->where('created_at', '>=', $now->subDays(30)),
            'this_month' => $query->whereMonth('created_at', $now->month)
                                 ->whereYear('created_at', $now->year),
            'last_month' => $query->whereMonth('created_at', $now->subMonth()->month)
                                 ->whereYear('created_at', $now->subMonth()->year),
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
              ->orWhere('properties', 'like', "%{$search}%")
              ->orWhereHas('causer', function (Builder $causerQuery) use ($search) {
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
     */
    public function getFormattedChangesAttribute(): array
    {
        $properties = $this->properties ?? [];
        $changes = [];

        if (isset($properties['old'], $properties['attributes'])) {
            $old = $properties['old'];
            $new = $properties['attributes'];

            foreach ($new as $key => $value) {
                $changes[] = [
                    'field' => $key,
                    'old' => $old[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }

    /**
     * Get causer name with fallback.
     */
    public function getCauserNameAttribute(): string
    {
        if (!$this->causer) {
            return 'System';
        }

        return $this->causer->name ?? $this->causer->email ?? 'Unknown User';
    }

    /**
     * Get subject name with fallback.
     */
    public function getSubjectNameAttribute(): string
    {
        if (!$this->subject) {
            return 'Unknown';
        }

        return $this->subject->name ??
               $this->subject->title ??
               class_basename($this->subject_type) . " #{$this->subject_id}";
    }

    /**
     * Check if activity has property changes.
     */
    public function hasPropertyChanges(): bool
    {
        $properties = $this->properties ?? [];
        return isset($properties['old']) && isset($properties['attributes']);
    }

    /**
     * Get summary of changes.
     */
    public function getChangesSummary(): string
    {
        if (!$this->hasPropertyChanges()) {
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
