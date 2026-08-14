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
