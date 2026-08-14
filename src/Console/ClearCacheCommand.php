<?php

namespace MuhammadSadeeq\ActivitylogUi\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use MuhammadSadeeq\ActivitylogUi\Services\ActivitylogService;

class ClearCacheCommand extends Command
{
    protected $signature = 'activitylog-ui:clear-cache';

    protected $description = 'Clear the Activity Log UI filter and analytics caches';

    /**
     * Cache keys written before the payloads were versioned. Left behind by an
     * upgrade, they are never read again but still occupy the store until their
     * TTL expires, so clear them too.
     *
     * @var array<int, string>
     */
    protected array $legacyKeys = [
        'causers',
        'subject_types',
        'event_types',
        'event_types_with_styling',
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
        // enumerated. They are versioned and validated on read, and expire on
        // their own; say so rather than implying everything is gone.
        $this->line('Analytics caches are keyed per filter set and expire on their own TTL.');

        return self::SUCCESS;
    }
}
