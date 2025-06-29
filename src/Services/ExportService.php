<?php

namespace MuhammadSadeeq\ActivitylogUi\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use MuhammadSadeeq\ActivitylogUi\Models\Activity;
use MuhammadSadeeq\ActivitylogUi\Exports\ActivitiesExport;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportService
{
    protected ActivitylogService $activitylogService;

    public function __construct(ActivitylogService $activitylogService)
    {
        $this->activitylogService = $activitylogService;
    }

    /**
     * Export activities to specified format.
     */
    public function export(array $filters, string $format, array $options = []): string
    {
        $activities = $this->getActivitiesForExport($filters, $options);

        return match ($format) {
            'csv' => $this->exportToCsv($activities, $options),
            'xlsx' => $this->exportToExcel($activities, $options),
            'pdf' => $this->exportToPdf($activities, $options),
            'json' => $this->exportToJson($activities, $options),
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /**
     * Get activities for export with proper filtering.
     */
    protected function getActivitiesForExport(array $filters, array $options): Collection
    {
        $maxRecords = config('activitylog-ui.exports.max_records', 10000);

        // Don't override the limit if filters are applied - get all filtered results up to max
        if (!empty($filters)) {
            // When filters are applied, get all matching records (up to max limit)
            $limit = $maxRecords;
        } else {
            // Only limit if no filters applied
            $limit = min($options['limit'] ?? $maxRecords, $maxRecords);
        }

        // Get filtered activities - this is crucial for proper filtering
        $activities = $this->activitylogService->getActivities($filters, $limit);

        // Log for debugging
        \Log::info('Getting activities for export', [
            'filters_applied' => $filters,
            'limit_used' => $limit,
            'total_found' => $activities->total(),
            'collection_count' => $activities->getCollection()->count()
        ]);

        return $activities->getCollection();
    }

    /**
     * Export to CSV format.
     */
    protected function exportToCsv(Collection $activities, array $options): string
    {
        $filename = $this->generateFilename('csv');
        $path = $this->getExportPath($filename);

        $csvData = $this->prepareCsvData($activities, $options);

        $handle = fopen(Storage::path($path), 'w');

        // Write header
        if (!empty($csvData)) {
            fputcsv($handle, array_keys($csvData[0]));
        }

        // Write data
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }

    /**
     * Export to Excel format (xlsx).
     */
    protected function exportToExcel(Collection $activities, array $options): string
    {
        // Check if Laravel Excel is available
        if (!class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            // Fallback to CSV format
            return $this->exportToCsv($activities, $options);
        }

        $filename = $this->generateFilename('xlsx');
        $path = $this->getExportPath($filename);

        Excel::store(new ActivitiesExport($activities, $options), $path);

        return $path;
    }

    /**
     * Export to PDF format.
     */
    protected function exportToPdf(Collection $activities, array $options): string
    {
        // Check if DomPDF is available
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            // Fallback to JSON format
            return $this->exportToJson($activities, $options);
        }

        $filename = $this->generateFilename('pdf');
        $path = $this->getExportPath($filename);

        $data = [
            'activities' => $activities,
            'title' => $options['title'] ?? 'Activity Log Report',
            'generated_at' => now(),
            'filters_applied' => $options['applied_filters'] ?? [],
            'total_count' => $activities->count(),
            'export_options' => $options,
        ];

        $pdf = Pdf::loadView('activitylog-ui::exports.pdf', $data);

        if ($options['orientation'] ?? 'portrait' === 'landscape') {
            $pdf->setPaper('a4', 'landscape');
        }

        Storage::put($path, $pdf->output());

        return $path;
    }

    /**
     * Export to JSON format.
     */
    protected function exportToJson(Collection $activities, array $options): string
    {
        $filename = $this->generateFilename('json');
        $path = $this->getExportPath($filename);

        $data = [
            'export_info' => [
                'generated_at' => now()->toISOString(),
                'total_records' => $activities->count(),
                'filters_applied' => $options['applied_filters'] ?? [],
                'export_options' => $options,
                'version' => '1.0',
            ],
            'activities' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'log_name' => $activity->log_name,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'subject_type' => $activity->subject_type,
                    'subject_id' => $activity->subject_id,
                    'causer_type' => $activity->causer_type,
                    'causer_id' => $activity->causer_id,
                    'properties' => $activity->properties,
                    'created_at' => $activity->created_at->toISOString(),
                    'causer_name' => $activity->causer_name,
                    'subject_name' => $activity->subject_name,
                    'changes_summary' => $activity->getChangesSummary(),
                ];
            }),
        ];

        Storage::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    /**
     * Prepare CSV data array.
     */
    protected function prepareCsvData(Collection $activities, array $options): array
    {
        $columns = $options['columns'] ?? [
            'id', 'date_time', 'causer', 'event', 'subject', 'description', 'changes'
        ];

        return $activities->map(function ($activity) use ($columns) {
            $row = [];

            foreach ($columns as $column) {
                $row[$column] = match ($column) {
                    'id' => $activity->id,
                    'date_time' => $activity->created_at->format('Y-m-d H:i:s'),
                    'causer' => $activity->causer_name ?? 'System',
                    'event' => $activity->event ?? 'unknown',
                    'subject' => $activity->subject_type ?
                        $activity->subject_type . ' #' . $activity->subject_id :
                        'N/A',
                    'description' => $activity->description,
                    'changes' => $activity->hasPropertyChanges() ?
                        $activity->getChangesSummary() :
                        'No changes tracked',
                    'properties' => json_encode($activity->properties),
                    default => $activity->{$column} ?? '',
                };
            }

            return $row;
        })->toArray();
    }

    /**
     * Generate unique filename for export.
     */
    protected function generateFilename(string $extension): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = substr(md5(uniqid()), 0, 8);

        return "activity_log_export_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Get full export path.
     */
    protected function getExportPath(string $filename): string
    {
        $basePath = config('activitylog-ui.exports.path', 'exports/activity-logs');
        return "{$basePath}/{$filename}";
    }

    /**
     * Get download URL for exported file.
     */
    public function getDownloadUrl(string $path): string
    {
        $disk = config('activitylog-ui.exports.disk', 'local');

        if ($disk === 'local') {
            return route('activitylog-ui.export.download', ['path' => base64_encode($path)]);
        }

        return Storage::disk($disk)->url($path);
    }

    /**
     * Clean up old export files.
     */
    public function cleanupOldExports(): int
    {
        // Check if cleanup is enabled
        if (!config('activitylog-ui.exports.cleanup.enabled', true)) {
            return 0;
        }

        $hours = config('activitylog-ui.exports.cleanup.after_hours', 24);
        $cutoff = now()->subHours($hours);

        $basePath = config('activitylog-ui.exports.path', 'exports/activity-logs');
        $files = Storage::files($basePath);

        $deletedCount = 0;

        foreach ($files as $file) {
            $lastModified = Storage::lastModified($file);

            if ($lastModified < $cutoff->timestamp) {
                Storage::delete($file);
                $deletedCount++;
            }
        }

        \Log::info('Export files cleanup completed', [
            'deleted_count' => $deletedCount,
            'cutoff_time' => $cutoff->toISOString()
        ]);

        return $deletedCount;
    }

    /**
     * Get export progress for queued exports.
     */
    public function getExportProgress(string $jobId): array
    {
        // Get job status from cache
        $status = cache()->get("export_job_{$jobId}");

        if (!$status) {
            return [
                'job_id' => $jobId,
                'status' => 'not_found',
                'progress' => 0,
                'message' => 'Export job not found. It may have expired or been completed.',
                'download_url' => null,
                'created_at' => null,
                'updated_at' => now()->toISOString(),
            ];
        }

        return $status;
    }

    /**
     * Get filtered record count for given filters.
     */
    public function getFilteredRecordCount(array $filters): int
    {
        // Get accurate filtered count - this is crucial for proper filtering
        $activities = $this->activitylogService->getActivities($filters, 1);

        $count = $activities->total();

        \Log::info('Filtered record count', [
            'filters' => $filters,
            'count' => $count
        ]);

        return $count;
    }

    /**
     * Validate export parameters.
     */
    public function validateExportRequest(array $filters, string $format, array $options = [], ?int $filteredCount = null): array
    {
        $errors = [];

        // Validate format
        $allowedFormats = config('activitylog-ui.exports.enabled_formats', ['csv', 'xlsx', 'pdf', 'json']);
        if (!in_array($format, $allowedFormats)) {
            $errors[] = "Export format '{$format}' is not enabled.";
        }

        // Get filtered count if not provided
        if ($filteredCount === null) {
            $filteredCount = $this->getFilteredRecordCount($filters);
        }

        // Validate record limit against filtered results
        $maxRecords = config('activitylog-ui.exports.max_records', 10000);
        if (isset($options['limit']) && $options['limit'] > $maxRecords) {
            $errors[] = "Export limit cannot exceed {$maxRecords} records.";
        }

        // Validate against system limits only - no UX suggestions
        if ($filteredCount > $maxRecords) {
            $errors[] = "Cannot export {$filteredCount} records. Maximum export limit is {$maxRecords} records.";
        }

        return $errors;
    }

    /**
     * Get estimated record count for given filters.
     * @deprecated Use getFilteredRecordCount instead
     */
    public function getEstimatedRecordCount(array $filters): int
    {
        return $this->getFilteredRecordCount($filters);
    }

    /**
     * Queue an export job for large datasets.
     */
    public function queueExport(array $filters, string $format, array $options = [], ?int $userId = null): string
    {
        $jobId = uniqid('export_');

        // Auto-cleanup old files if enabled
        if (config('activitylog-ui.exports.cleanup.auto_run', true)) {
            $this->cleanupOldExports();
        }

        try {
            // Create initial job status
            $initialStatus = [
                'job_id' => $jobId,
                'status' => 'pending',
                'message' => 'Export queued for processing...',
                'progress' => 0,
                'download_url' => null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            // Store initial status in cache
            cache()->put("export_job_{$jobId}", $initialStatus, now()->addHours(24));

            // Dispatch the job
            $job = new \MuhammadSadeeq\ActivitylogUi\Jobs\ExportActivitiesJob($jobId, $filters, $format, $options, $userId);
            dispatch($job);

            \Log::info('Export job queued successfully', [
                'job_id' => $jobId,
                'format' => $format,
                'filters' => $filters,
                'user_id' => $userId
            ]);

            return $jobId;

        } catch (\Exception $e) {
            \Log::error('Failed to queue export job', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            // Update status to failed
            cache()->put("export_job_{$jobId}", [
                'job_id' => $jobId,
                'status' => 'failed',
                'message' => 'Failed to queue export: ' . $e->getMessage(),
                'progress' => 0,
                'download_url' => null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ], now()->addHours(24));

            throw $e;
        }
    }
}
