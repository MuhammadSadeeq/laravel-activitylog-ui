<?php

namespace MuhammadSadeeq\ActivitylogUi\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use MuhammadSadeeq\ActivitylogUi\Services\ExportService;

class ExportController extends Controller
{
    protected ExportService $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Export activities in specified format.
     */
    public function export(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        $request->validate([
            'format' => 'required|string|in:' . implode(',', config('activitylog-ui.exports.enabled_formats')),
            'filters' => 'array',
            'options' => 'array',
        ]);

        $format = $request->input('format');
        $options = $request->input('options', []);

        // Filters arrive nested in a JSON body, so they never pass through the
        // dashboard's own extraction. Normalise them the same way: `filters` is
        // only validated as an array, and a nested array inside it reaches a
        // string-typed scope and throws before the try block below.
        $filters = app(ActivityLogController::class)
            ->normalizeFilters(is_array($request->input('filters')) ? $request->input('filters') : []);

        // Add filters to options for proper tracking
        $options['applied_filters'] = $filters;

        // Get filtered record count first
        $filteredCount = $this->exportService->getFilteredRecordCount($filters);

        // Log export attempt for debugging
        \Log::info('Export requested', [
            'format' => $format,
            'filters_applied' => $filters,
            'filtered_record_count' => $filteredCount,
            'user_id' => $request->user()?->id
        ]);

        // Validate export request with actual filtered count
        $errors = $this->exportService->validateExportRequest($filters, $format, $options, $filteredCount);
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Export validation failed.',
                'errors' => $errors,
                'filtered_count' => $filteredCount,
            ], 422);
        }

        try {
            // Check if export should be queued based on filtered count
            $shouldQueue = config('activitylog-ui.exports.queue.enabled', false);
            $queueThreshold = config('activitylog-ui.exports.queue.threshold', 1000);

            if ($shouldQueue && $filteredCount > $queueThreshold) {
                // Queue the export
                $jobId = $this->exportService->queueExport(
                    $filters,
                    $format,
                    $options,
                    $request->user()?->id
                );

                return response()->json([
                    'success' => true,
                    'message' => "Export queued successfully. Processing {$filteredCount} filtered records.",
                    'job_id' => $jobId,
                    'queued' => true,
                    'filtered_count' => $filteredCount,
                ]);
            }

            // Export immediately with filters
            $filePath = $this->exportService->export($filters, $format, $options);
            $downloadUrl = $this->exportService->getDownloadUrl($filePath);

            return response()->json([
                'success' => true,
                'message' => "Export completed successfully. {$filteredCount} records exported.",
                'download_url' => $downloadUrl,
                'file_path' => $filePath,
                'queued' => false,
                'filtered_count' => $filteredCount,
            ]);

        } catch (\Throwable $e) {
            \Log::error('Export failed', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'format' => $format
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Download exported file.
     */
    public function download(Request $request): StreamedResponse
    {
        $this->authorize('viewActivityLogUi');

        $request->validate([
            'path' => 'required|string',
        ]);

        $path = base64_decode($request->input('path'), true);

        if ($path === false) {
            abort(400, 'Invalid file path.');
        }

        // Confine the path to the exports directory. A prefix check alone accepts
        // "exports/activity-logs/../../../.env": Flysystem rejects traversal in
        // practice, but relying on that leaves the guard here saying something it
        // does not enforce.
        //
        // The directory comes from the service so the two agree: they each used to
        // normalise the configured value differently, and a trailing slash was
        // enough to make every download of a successfully written file 403.
        $exportPath = $this->exportService->exportDirectory();
        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if (!str_starts_with($normalized, $exportPath . '/')) {
            abort(403, 'Invalid file path.');
        }

        // Segment-wise, so '..' is rejected as a path component rather than as a
        // substring — a legitimately named file may contain dots.
        $segments = explode('/', $normalized);

        if (in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) {
            abort(403, 'Invalid file path.');
        }

        // Exports are only ever written in these formats. Anything else under the
        // directory belongs to the host, and this endpoint is not a file browser.
        if (!in_array(strtolower(pathinfo($normalized, PATHINFO_EXTENSION)), ['csv', 'xlsx', 'pdf', 'json'], true)) {
            abort(403, 'Invalid file path.');
        }

        // Read from the configured disk, not whichever one happens to be default:
        // the file was written to the configured one.
        $disk = $this->exportService->disk();

        if (!$disk->exists($normalized)) {
            abort(404, 'File not found.');
        }

        $filename = basename($normalized);
        $mimeType = $this->getMimeType($normalized);

        // download() streams via readStream() and supplies the file size, rather
        // than reading the whole export into memory to print it.
        return $disk->download($normalized, $filename, ['Content-Type' => $mimeType]);
    }

    /**
     * Get export progress for queued jobs.
     */
    public function progress(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        $request->validate([
            'job_id' => 'required|string',
        ]);

        $jobId = $request->input('job_id');
        $progress = $this->exportService->getExportProgress($jobId);

        return response()->json([
            'success' => true,
            'data' => $progress,
        ]);
    }

    /**
     * Get available export formats.
     */
    public function formats(): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        $formats = config('activitylog-ui.exports.enabled_formats', []);
        $maxRecords = config('activitylog-ui.exports.max_records', 10000);

        $formatsWithDetails = collect($formats)->map(function ($format) {
            return [
                'value' => $format,
                'label' => ucfirst($format),
                'description' => $this->getFormatDescription($format),
                'icon' => $this->getFormatIcon($format),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'formats' => $formatsWithDetails,
                'max_records' => $maxRecords,
                'queue_enabled' => config('activitylog-ui.exports.queue', true),
            ],
        ]);
    }

    /**
     * Cleanup old export files.
     */
    public function cleanup(): JsonResponse
    {
        $this->authorize('viewActivityLogUi');

        $deletedCount = $this->exportService->cleanupOldExports();

        return response()->json([
            'success' => true,
            'message' => "Cleaned up {$deletedCount} old export files.",
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * Get MIME type for file.
     */
    protected function getMimeType(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match ($extension) {
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }

    /**
     * Get format description.
     */
    protected function getFormatDescription(string $format): string
    {
        return match ($format) {
            'csv' => 'Comma-separated values file for spreadsheet applications',
            'xlsx' => 'Microsoft Excel workbook with formatting',
            'pdf' => 'Formatted PDF report for printing or sharing',
            'json' => 'Machine-readable JSON format for API integration',
            default => 'Data export in ' . ucfirst($format) . ' format',
        };
    }

    /**
     * Get format icon.
     */
    protected function getFormatIcon(string $format): string
    {
        return match ($format) {
            'csv' => 'document-text',
            'xlsx' => 'table-cells',
            'pdf' => 'document',
            'json' => 'code-bracket',
            default => 'document',
        };
    }

    /**
     * Authorize access to activity log UI.
     */
    protected function authorize(string $ability): void
    {
        if (!config('activitylog-ui.authorization.enabled', true)) {
            return;
        }

        $gate = config('activitylog-ui.authorization.gate', 'viewActivityLogUi');

        if (method_exists($this, 'authorizeForUser')) {
            $this->authorizeForUser(request()->user(), $gate);
        } else {
            abort_unless(request()->user()?->can($gate), 403);
        }
    }
}
