<?php

namespace MuhammadSadeeq\ActivitylogUi\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use MuhammadSadeeq\ActivitylogUi\Services\ActivitylogService;

class ClearCacheCommand extends Command
{
    protected $signature = 'activitylog-ui:clear-cache';

    protected $description = 'Clear the Activity Log UI filter option caches';

    /**
     * Filter-option keys from earlier versions. Left behind by an upgrade, they
     * are never read again but still occupy the store until their TTL expires.
     *
     * Includes both the original unversioned names and the v2 names, since the
     * causer list is now deduplicated by type and id and a v2 payload is still
     * missing every causer that shared an id with another type.
     *
     * @var array<int, string>
     */
    protected array $legacyKeys = [
        'causers',
        'subject_types',
        'event_types',
        'event_types_with_styling',
        'v2.causers',
        'v2.subject_types',
        'v2.event_types',
        'v2.event_types_with_styling',
    ];

    public function handle(ActivitylogService $activitylog): int
    {
        $prefix = config('activitylog-ui.performance.cache_prefix');

        $activitylog->flushFilterOptions();
        $this->info('Cleared the filter option caches.');

        $cleared = 0;

        foreach ($this->legacyKeys as $name) {
            $key = "{$prefix}.{$name}";

            // Counted via has() rather than the return of forget(), which several
            // stores answer true for regardless of whether anything was there.
            if (Cache::has($key)) {
                $cleared++;
            }

            Cache::forget($key);
        }

        if ($cleared > 0) {
            $this->info("Cleared {$cleared} cache " . ($cleared === 1 ? 'entry' : 'entries') . ' written before the keys were versioned.');
        }

        // Analytics keys carry a filter hash or a user id, so they cannot be
        // enumerated and this command does not clear them. Said plainly rather
        // than letting the command imply it cleared everything.
        $this->line('Analytics caches are keyed per filter set and per user, so they are not cleared here; they expire on their own TTL.');

        return self::SUCCESS;
    }
}
