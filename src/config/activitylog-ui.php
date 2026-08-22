<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activity Log UI Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all the configuration options for the Activity Log UI
    | package. You can customize the behavior, appearance, and features of
    | the activity log interface according to your needs.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Middleware is auto-determined:
    | - authorization.enabled=false: ['web'] only (public access)
    | - authorization.enabled=true: ['web', 'auth', ActivityLogAccessMiddleware]
    | - Set custom middleware array to override auto-detection
    |
    */
    'route' => [
        'prefix' => 'activitylog-ui',
        'name' => 'activitylog-ui.',
        'middleware' => null, // Auto-determined based on authorization.enabled, or set custom middleware
        'domain' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Configuration
    |--------------------------------------------------------------------------
    |
    | When enabled=true:  requires authentication, then the gate below.
    | When enabled=false: the entire UI is public. Anyone who can reach the URL
    |                     can read who did what, when, and to which record.
    |
    | The default gate allows any authenticated user, and narrows to the
    | access.allowed_users / access.allowed_roles lists once you set them.
    | Turn this off only for local development.
    |
    */
    'authorization' => [
        'enabled' => env('ACTIVITYLOG_UI_AUTHORIZATION', true),
        'gate' => 'viewActivityLogUi',
        'policy' => null,
        'guard' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Control Configuration
    |--------------------------------------------------------------------------
    |
    | These controls work independently of authorization.enabled:
    | - If either allowed_users or allowed_roles is defined, authentication is required
    | - If both are empty, access is open (public if authorization.enabled=false)
    |
    */
    'access' => [
        // List of user emails that are allowed to access the UI
        'allowed_users' => [
            // 'admin@example.com',
            // 'manager@example.com',
        ],

        // List of roles that are allowed to access the UI
        // Requires a role-based package like Spatie Permission
        'allowed_roles' => [
            // 'admin',
            // 'manager',
            // 'developer',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    */
    'ui' => [
        // Attributes tried, in order, when showing who caused an activity.
        // Applications that do not key their users on `name` would otherwise see
        // every causer rendered as "Unknown".
        'causer_name_attributes' => ['name', 'email'],

        'title' => 'Activity Log',
        'brand' => 'ActivityLog UI',
        'logo' => null,
        'default_view' => 'table', // table, timeline
        'per_page_options' => [10, 25, 50, 100],
        'default_per_page' => 25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features Configuration
    |--------------------------------------------------------------------------
    */
    'features' => [
        'analytics' => true,
        'exports' => true,
        'saved_views' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    */
    'exports' => [
        // Enabled export formats
        'enabled_formats' => ['csv', 'xlsx', 'pdf', 'json'],

        // Maximum number of records that can be exported in a single request
        // WARNING: Increasing this limit may cause memory issues, timeouts, or large file sizes
        // Consider using queued exports for large datasets (queue.enabled = true)
        // Recommended: 10000 for web exports, 50000+ for queued exports only
        // Set ACTIVITYLOG_MAX_EXPORT_RECORDS in .env to override
        'max_records' => env('ACTIVITYLOG_MAX_EXPORT_RECORDS', 10000),

        // Storage configuration
        'disk' => 'local',
        'path' => 'exports/activity-logs',

        // Queue configuration for large exports
        'queue' => [
            // Enable/disable queuing (false by default - exports run synchronously)
            'enabled' => false,

            // Threshold for queuing exports (records count)
            // Exports above this limit will be queued if queuing is enabled
            'threshold' => 1000,

            // Queue connection to use for export jobs
            'connection' => null, // Uses default queue connection

            // Queue name for export jobs
            // To process: php artisan queue:work --queue=exports
            // Or for mixed: php artisan queue:work --queue=exports,default
            'queue_name' => 'exports',

            // Job timeout in seconds
            'timeout' => 300, // 5 minutes

            // Job retry attempts
            'tries' => 3,
        ],

        // File cleanup configuration
        'cleanup' => [
            // Automatically cleanup old export files
            'enabled' => true,

            // Delete files older than this many hours
            'after_hours' => 24,

            // Run cleanup automatically when creating new exports
            'auto_run' => true,
        ],

        // Export notification settings
        'notifications' => [
            // Notify users when queued exports are complete
            'enabled' => true,

            // Notification channels to use
            'channels' => ['mail'],

            // Email settings for export notifications
            'mail' => [
                'from_address' => null, // Uses default app mail from
                'from_name' => 'Activity Log Exports',
                'subject' => 'Your Activity Log Export is Ready',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'cache_duration' => 3600, // seconds

        // Distinct event series the trend chart draws before the rest are summed
        // into a single "Other" line. An application can log any number of event
        // names, and past about six the palette repeats — two lines share a
        // colour and the legend stops identifying anything.
        'max_chart_series' => 6,
        'chart_colors' => [
            'created' => '#10b981',
            'updated' => '#3b82f6',
            'deleted' => '#ef4444',
            'restored' => '#f59e0b',
            'custom' => '#8b5cf6',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filtering Configuration
    |--------------------------------------------------------------------------
    */
    'filters' => [
        'date_presets' => [
            'all' => 'All time',
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'last_7_days' => 'Last 7 days',
            'last_30_days' => 'Last 30 days',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'custom' => 'Custom range',
        ],
        'max_saved_views' => 10,

        // Include causer email addresses in the causer filter dropdown, which
        // makes the "Search users" box match on email as well as name.
        //
        // Off by default: the filter options endpoint returns every distinct
        // causer, and it is unauthenticated unless 'authorization.enabled' is
        // turned on, so enabling this would publish a complete email directory.
        'expose_causer_email' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'cache_prefix' => 'activitylog_ui',

        // Seconds to cache the filter dropdown options (causers, subject types,
        // event types). New causers will not appear until this expires; call
        // ActivitylogService::flushFilterOptions() after a bulk import to refresh
        // them immediately.
        'cache_ttl' => 3600,

        // When those options expire, one request rebuilds them and the others
        // wait this long for its result rather than all running the same scan.
        // A waiter that times out rebuilds them itself, so raising this trades
        // slower worst-case requests for less duplicated work.
        'filter_lock_wait' => 3,

        // How long that rebuild may hold the lock. Wants to exceed the slowest
        // expected scan: too low and a long rebuild loses the lock partway
        // through, letting a second request start the same work; too high and a
        // worker killed mid-scan blocks rebuilds until it expires.
        'filter_lock_ttl' => 30,

        'eager_load_relations' => ['causer', 'subject'],
    ],
];
