# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Breaking Changes
- `authorization.enabled` now defaults to `true`. Previously the UI shipped fully public — a default install served the entire audit log to unauthenticated visitors. Installs that published the config keep their own value and are unaffected; installs that never published it will now require a logged-in user. Set `ACTIVITYLOG_UI_AUTHORIZATION=false` to restore the old behaviour, and see the authorization section of the README for the full precedence order.
- `access.allowed_users` and `access.allowed_roles` are now actually enforced when `authorization.enabled` is `false`. The middleware that enforces them was previously only registered when authorization was enabled, so those lists had no effect at all in that combination.
- `route.middleware` no longer replaces the authentication and access middleware; it replaces the base stack only, and the security middleware is appended afterwards.
- `AnalyticsService::getUserActivityProfile()` returns `first_activity` and `last_activity` as ISO 8601 strings rather than Carbon instances. The default HTTP response is unchanged; direct PHP callers and applications using `Carbon::serializeUsing()` will see the difference.
- Out-of-range and unusable request parameters now return `422` instead of being silently clamped or dropped. `per_page=999999` used to become 100 and an unusable `causer_id` became no causer filter at all, each answering `200` as though nothing had changed — and a dropped filter shows *more* of the audit log than was asked for. Affects `page`, `per_page`, `anchor_id`, `causer_id`, `subject_id`, `event_types` and the single-value filters, on the list, analytics and export endpoints. Omitted or empty parameters still use the default. See UPGRADING.md.
- Exports are now scoped to the user who created them. Both the download and the progress endpoint refuse anyone else, and job ids are random rather than `uniqid()`. Shared export URLs will stop working across users.

### Changed
- The UI no longer loads the Tailwind Play CDN, Chart.js or a webfont at page load — roughly 630KB of third-party assets. It ships one stylesheet served on a versioned route by the package itself (about 6KB over the wire), with no build step and nothing to publish. Chart.js is fetched only when a chart is drawn.
- The activity table was reworked for scanning: the date is a heading per day rather than a column on every row, rows are about half as tall, the record acted on is the primary line with the description beneath it only when it differs from the event, and below 960px rows stack into blocks instead of scrolling the page sideways.
- Activity listings are ordered by `created_at` with the primary key as a tiebreak, rather than by the key alone. Backdated or imported activities previously appeared out of chronological order.

### Fixed
- Fixed the filter option caches returning `__PHP_Incomplete_Class` and taking the dashboard down (#12). They now store plain arrays, every read is validated before use so a bad entry is discarded and rebuilt, and the keys are versioned so an upgrade cannot read what an older release wrote. Reported by [@djemmal-nour-el-islam](https://github.com/djemmal-nour-el-islam), who also identified `getEventTypesWithStyling()` as affected.
- Fixed the UI ignoring a custom `activitylog.activity_model` (#9). The table, connection and key metadata now come from the configured model, including causers that live on a different connection from the log itself. Reported by [@fbmfbm](https://github.com/fbmfbm).
- Fixed four of the usability problems in #10, reported by [@femto-code](https://github.com/femto-code): "Load More" no longer jumps back to the top of the page, the repeated "Loaded N activities" toasts are gone, the causer name is configurable through `ui.causer_name_attributes` instead of showing "Unknown", and the timeline hint can be dismissed. The mobile layout quirks are addressed by the interface rework, which stacks each row into a block below 960px rather than scrolling the page sideways.

### Added
- `php artisan activitylog-ui:clear-cache` for clearing the filter option caches by hand.
- `analytics.max_chart_series` — how many event types the trend chart draws before the remainder are summed into a single "Other" series. Defaults to 6.
- `performance.filter_lock_wait` and `performance.filter_lock_ttl` — control the single-flight lock that stops every in-flight request rebuilding the filter caches at once when they expire.

## [2.0.1] - 2026-04-03

### Fixed
- Fixed frontend JSON parsing failures by surfacing non-JSON API responses with clearer diagnostics across activity, analytics, saved views, and export requests
- Fixed Alpine.js null-handling issues when switching between table and timeline views
- Fixed custom properties rendering when `activity.properties` is null in the timeline and activity detail modal
- Fixed missing favicon 404s by only rendering favicon links when published assets are available

## [2.0.0] - 2026-04-03

### Breaking Changes
- Requires PHP 8.4+, Laravel 12+, and Spatie laravel-activitylog v5
- Batch UUID feature removed entirely (Spatie v5 removes batch system)
- Activity attribute changes now read from `attribute_changes` column instead of `properties`
- `hasPropertyChanges()` deprecated in favor of `hasAttributeChanges()`

### Changed
- Properties column in UI now shows only custom data; attribute changes displayed separately
- Timeline view and detail modal now show "Attribute Changes" and "Custom Properties" as distinct sections
- Search now covers `attribute_changes` column in addition to `properties` and `description`
- Export JSON output now includes `attribute_changes` field
- Schema column check now uses the model's database connection instead of the default connection

### Added
- `restored` event color in analytics chart defaults
- Separate "Attribute Changes" display section in activity detail modal
- Separate "Custom Properties" display section in timeline and detail views
- Legacy fallback: `hasAttributeChanges()` and `getFormattedChangesAttribute()` fall back to `properties.old`/`properties.attributes` for unmigrated rows
- Frontend legacy fallback: timeline and detail modal resolve changes from `attribute_changes` or `properties` transparently
- Legacy diff keys (`old`, `attributes`) are filtered from the Custom Properties panel to prevent duplication

### Removed
- Batch UUID filter from filter panel, table view, and all JavaScript state management
- `FiltersBatchUuid` trait
- `sanitizeUuid()` method from controller

## [1.3.1] - 2026-04-02

### Fixed
- Fixed Alpine.js TypeError by initializing filters in `init()` instead of during object construction

## [1.3.0] - 2026-03-06

### Added
- Added batch UUID filtering across the activity list and analytics dashboard
- Added a dedicated Batch UUID filter input with saved filter state
- Added clickable batch badges in the table view for quick drill-down into related activity batches

### Fixed
- Fixed `causer_id` and `subject_id` filtering to support both string and integer identifiers
- Fixed activity causer resolution to exclude global scopes when loading related users
- Fixed PHP 8.4 warnings in the export service

## [1.2.0] - 2025-08-04

### Performance
- Optimized database queries by changing sorting from `created_at` to `id` for faster loading in large databases
- Improved performance for activity listing, recent activities, and related activities queries

## [1.1.0] - 2025-07-26

### Added
- User dropdown menu in navigation header
- Logout functionality with proper Laravel authentication
- User information display (name and email) in dropdown
- Smooth animations and transitions for dropdown interactions
- Dark mode support for dropdown components
- Click-away functionality to close dropdown

### Changed
- Updated version constant in service provider for better version management
- Improved user experience with interactive navigation elements

## [1.0.0] - 2025-07-07

### Added
- Initial release of Laravel ActivityLog UI package
- Beautiful, modern UI for Spatie's Activity Log
- Advanced filtering capabilities
- Analytics dashboard with charts
- Real-time activity monitoring
- Export functionality (CSV, Excel, PDF, JSON)
- Timeline and table views
- Dark mode support
- Responsive design
- Saved views functionality
- User access control
- Comprehensive documentation 
