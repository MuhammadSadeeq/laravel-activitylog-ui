<?php

namespace MuhammadSadeeq\ActivitylogUi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use MuhammadSadeeq\ActivitylogUi\Services\ExportService;
use MuhammadSadeeq\ActivitylogUi\Mail\ExportCompletedMail;

class ExportActivitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout;
    public $tries;

    protected string $jobId;
    protected array $filters;
    protected string $format;
    protected array $options;
    protected ?int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $jobId, array $filters, string $format, array $options = [], ?int $userId = null)
    {
        $this->jobId = $jobId;
        $this->filters = $filters;
        $this->format = $format;
        $this->options = $options;
        $this->userId = $userId;

        // Set job configuration from config
        $this->timeout = config('activitylog-ui.exports.queue.timeout', 300);
        $this->tries = config('activitylog-ui.exports.queue.tries', 3);

        // Set queue connection and name
        $connection = config('activitylog-ui.exports.queue.connection');
        $queueName = config('activitylog-ui.exports.queue.queue_name', 'exports');

        if ($connection) {
            $this->connection = $connection;
        }

        if ($queueName) {
            $this->queue = $queueName;
        }
    }

    /**
     * Execute the job.
     */
    public function handle(ExportService $exportService): void
    {
        try {
            Log::info('Starting queued export job', [
                'job_id' => $this->jobId,
                'format' => $this->format,
                'filters' => $this->filters,
                'user_id' => $this->userId
            ]);

            // Update job status to processing
            $this->updateJobStatus('processing', 'Starting export...');

            // Perform the actual export
            $filePath = $exportService->export($this->filters, $this->format, $this->options);
            $downloadUrl = $exportService->getDownloadUrl($filePath);

            // Update job status to completed
            $this->updateJobStatus('completed', 'Export completed successfully', $downloadUrl);

            // Send notification if enabled and user exists
            if ($this->shouldSendNotification()) {
                $this->sendCompletionNotification($downloadUrl, $filePath);
            }

            Log::info('Queued export job completed successfully', [
                'job_id' => $this->jobId,
                'file_path' => $filePath,
                'download_url' => $downloadUrl
            ]);

        } catch (\Exception $e) {
            Log::error('Queued export job failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update job status to failed
            $this->updateJobStatus('failed', 'Export failed: ' . $e->getMessage());

            // Re-throw the exception to mark the job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Export job failed permanently', [
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        $this->updateJobStatus('failed', 'Export failed after ' . $this->attempts() . ' attempts: ' . $exception->getMessage());
    }

    /**
     * Update job status in cache/storage.
     */
    protected function updateJobStatus(string $status, string $message, ?string $downloadUrl = null): void
    {
        $data = [
            'job_id' => $this->jobId,
            'status' => $status,
            'message' => $message,
            'progress' => $status === 'completed' ? 100 : ($status === 'processing' ? 50 : 0),
            'download_url' => $downloadUrl,
            'updated_at' => now()->toISOString(),
        ];

        // Store job status (you might want to use a more persistent storage in production)
        cache()->put("export_job_{$this->jobId}", $data, now()->addHours(24));
    }

    /**
     * Check if completion notification should be sent.
     */
    protected function shouldSendNotification(): bool
    {
        return $this->userId &&
               config('activitylog-ui.exports.notifications.enabled', true) &&
               in_array('mail', config('activitylog-ui.exports.notifications.channels', []));
    }

    /**
     * Send export completion notification.
     */
    protected function sendCompletionNotification(string $downloadUrl, string $filePath): void
    {
        try {
            // Get user model (assumes default Laravel User model)
            $userModel = config('auth.providers.users.model', \App\Models\User::class);
            $user = $userModel::find($this->userId);

            if (!$user) {
                Log::warning('Cannot send export notification - user not found', ['user_id' => $this->userId]);
                return;
            }

            // Create notification data
            $notificationData = [
                'user_name' => $user->name ?? $user->email,
                'export_format' => strtoupper($this->format),
                'download_url' => $downloadUrl,
                'file_name' => basename($filePath),
                'exported_at' => now()->format('F j, Y \a\t g:i A'),
                'job_id' => $this->jobId,
                'applied_filters' => $this->options['applied_filters'] ?? [],
            ];

            // Send email notification
            Mail::to($user->email)->send(new ExportCompletedMail($notificationData));

            Log::info('Export completion notification sent', [
                'job_id' => $this->jobId,
                'user_email' => $user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send export completion notification', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
