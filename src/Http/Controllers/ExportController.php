<?php

namespace MuhammadSadeeq\ActivitylogUi\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
            'format' => 'required|string|in:csv,xlsx,pdf,json',
            'filters' => 'array',
            'options' => 'array',
        ]);

        $format = $request->get('format');
        $filters = $request->get('filters', []);
        $options = $request->get('options', []);

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

        } catch (\Exception $e) {
            \Log::error('Export failed', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'format' => $format
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download exported file.
     */
    public function download(Request $request): BinaryFileResponse
    {
        $this->authorize('viewActivityLogUi');

        $request->validate([
            'path' => 'required|string',
        ]);

        $path = base64_decode($request->get('path'));

        // Security check: ensure path is within exports directory
        $exportPath = config('activitylog-ui.exports.path', 'exports/activity-logs');
        if (!str_starts_with($path, $exportPath)) {
            abort(403, 'Invalid file path.');
        }

        if (!Storage::exists($path)) {
            abort(404, 'File not found.');
        }

        $filename = basename($path);
        $mimeType = $this->getMimeType($path);

        return response()->download(
            Storage::path($path),
            $filename,
            ['Content-Type' => $mimeType]
        );
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

        $jobId = $request->get('job_id');
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
