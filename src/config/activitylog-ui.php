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
    */
    'route' => [
        'prefix' => 'activitylog-ui',
        'name' => 'activitylog-ui.',
        'middleware' => ['web', 'auth'],
        'domain' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Configuration
    |--------------------------------------------------------------------------
    */
    'authorization' => [
        'enabled' => false,
        'gate' => 'viewActivityLogUi',
        'policy' => null,
        'guard' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Control Configuration
    |--------------------------------------------------------------------------
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
        'title' => 'Activity Log',
        'brand' => 'ActivityLog UI',
        'logo' => null,
        'theme' => 'light', // light, dark, auto
        'default_view' => 'table', // table, timeline
        'per_page_options' => [10, 25, 50, 100],
        'default_per_page' => 25,
        'date_format' => 'Y-m-d H:i:s',
        'timezone' => null, // Uses app timezone if null
    ],

    /*
    |--------------------------------------------------------------------------
    | Features Configuration
    |--------------------------------------------------------------------------
    */
    'features' => [
        'analytics' => true,
        'exports' => true,
        'real_time' => true,
        'saved_views' => true,
        'user_profiles' => true,
        'notifications' => true,
        'advanced_search' => true,
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

        // Package dependencies configuration
        'requires_packages' => [
            'xlsx' => 'maatwebsite/excel',
            'pdf' => 'barryvdh/laravel-dompdf',
        ],

        // Fallback formats when required packages are missing
        'fallbacks' => [
            'xlsx' => 'csv',
            'pdf' => 'json',
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
    | Real-time Configuration
    |--------------------------------------------------------------------------
    */
    'realtime' => [
        'enabled' => true,
        'driver' => 'reverb', // reverb, pusher, redis
        'channel' => 'activity-log-updates',
        'refresh_interval' => 30, // seconds
        'max_items_in_feed' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'cache_duration' => 3600, // seconds
        'chart_colors' => [
            'created' => '#10b981',
            'updated' => '#3b82f6',
            'deleted' => '#ef4444',
            'custom' => '#8b5cf6',
        ],
        'summary_widgets' => [
            'total_activities',
            'activities_today',
            'top_users',
            'popular_models',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filtering Configuration
    |--------------------------------------------------------------------------
    */
    'filters' => [
        'date_presets' => [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'last_7_days' => 'Last 7 days',
            'last_30_days' => 'Last 30 days',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'custom' => 'Custom range',
        ],
        'searchable_fields' => [
            'description',
            'properties',
            'causer.name',
            'causer.email',
            'subject.title',
            'subject.name',
        ],
        'max_saved_views' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'redact_sensitive_fields' => true,
        'sensitive_field_patterns' => [
            'password',
            'token',
            'secret',
            'api_key',
            'private_key',
        ],
        'redaction_text' => '[REDACTED]',
        'audit_ui_access' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Customization Configuration
    |--------------------------------------------------------------------------
    */
    'customization' => [
        'custom_activity_renderers' => [],
        'custom_subject_links' => [],
        'custom_causer_links' => [],
        'blade_component_overrides' => [],
        'css_framework' => 'tailwind', // tailwind, bootstrap
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'cache_enabled' => true,
        'cache_prefix' => 'activitylog_ui',
        'eager_load_relations' => ['causer', 'subject'],
        'index_recommendations' => [
            'created_at',
            'causer_type',
            'causer_id',
            'subject_type',
            'subject_id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'enabled_channels' => ['mail', 'slack', 'discord'],
        'rules' => [
            // Define notification rules
        ],
        'slack' => [
            'webhook_url' => env('ACTIVITYLOG_SLACK_WEBHOOK', null),
            'channel' => '#activity-logs',
            'username' => 'Activity Log Bot',
        ],
        'discord' => [
            'webhook_url' => env('ACTIVITYLOG_DISCORD_WEBHOOK', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GDPR Compliance Configuration
    |--------------------------------------------------------------------------
    */
    'gdpr' => [
        'enabled' => false,
        'retention_days' => 365,
        'auto_cleanup' => false,
        'anonymize_instead_of_delete' => true,
    ],
];
