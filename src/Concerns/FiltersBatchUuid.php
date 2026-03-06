<?php

namespace MuhammadSadeeq\ActivitylogUi\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use MuhammadSadeeq\ActivitylogUi\Models\Activity;

trait FiltersBatchUuid
{
    protected static array $batchUuidSupport = [];

    protected function applyBatchUuidFilter(Builder $query, string $batchUuid): void
    {
        if (! $this->tableSupportsBatchUuid($query->getModel()->getTable())) {
            return;
        }

        if (method_exists($query->getModel(), 'scopeForBatch')) {
            $query->forBatch($batchUuid);
        } else {
            $query->where('batch_uuid', $batchUuid);
        }
    }

    protected function tableSupportsBatchUuid(?string $table = null): bool
    {
        $table ??= (new Activity())->getTable();

        if (! array_key_exists($table, static::$batchUuidSupport)) {
            static::$batchUuidSupport[$table] = Schema::hasColumn($table, 'batch_uuid');
        }

        return static::$batchUuidSupport[$table];
    }
}
