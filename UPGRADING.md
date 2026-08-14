# Upgrading from v1.x to v2.0

## Requirements

| | v1.x | v2.0 |
|---|---|---|
| PHP | ^8.1 | ^8.4 |
| Laravel | 10 / 11 / 12 | 12 / 13 |
| Spatie Activity Log | ^4.8 | ^5.0 |

## Before you begin

**Back up your `activity_log` table before running any migration.** This upgrade modifies audit history in place. If you need to roll back, you will need the backup.

```bash
# Example: dump the table before migrating
mysqldump -u root your_database activity_log > activity_log_backup.sql
```

## Step 1: Upgrade Spatie laravel-activitylog to v5

You must upgrade Spatie's package first. Follow their official [upgrade guide](https://github.com/spatie/laravel-activitylog/blob/main/UPGRADING.md).

**Important:** Run the migration before deploying the new package code. The v2 UI will work gracefully if the `attribute_changes` column doesn't exist yet, but you'll get the best experience once the migration is complete.

The key migration adds the `attribute_changes` column and copies data from `properties`:

```bash
php artisan make:migration upgrade_activity_log_to_v5
```

```php
public function up(): void
{
    // Phase 1: Add the new column
    Schema::table('activity_log', function (Blueprint $table) {
        $table->json('attribute_changes')->nullable()->after('causer_id');
    });

    // Phase 2: Copy (not move) change data to the new column
    // Legacy keys are preserved in properties for safety — clean up later if desired
    DB::table('activity_log')
        ->whereNotNull('properties')
        ->eachById(function ($activity) {
            $properties = json_decode($activity->properties, true);

            if (isset($properties['old']) || isset($properties['attributes'])) {
                $attributeChanges = array_filter([
                    'old' => $properties['old'] ?? null,
                    'attributes' => $properties['attributes'] ?? null,
                ]);

                DB::table('activity_log')
                    ->where('id', $activity->id)
                    ->update([
                        'attribute_changes' => json_encode($attributeChanges),
                    ]);
            }
        });

    // Phase 3: Drop batch_uuid if it exists (Spatie v5 removes the batch system)
    if (Schema::hasColumn('activity_log', 'batch_uuid')) {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn('batch_uuid');
        });
    }
}
```

Once you have verified the migration is complete and your application works correctly, you can optionally clean up the legacy keys from `properties` in a separate migration.

## Step 1b: Update your own models

Spatie v5 moved the traits your models use. Every model still importing the old
path will fatal with `Trait "Spatie\Activitylog\Traits\LogsActivity" not found`
as soon as the upgrade lands, so do this in the same deploy.

| v4 | v5 |
|---|---|
| `Spatie\Activitylog\Traits\LogsActivity` | `Spatie\Activitylog\Models\Concerns\LogsActivity` |
| `Spatie\Activitylog\Traits\CausesActivity` | `Spatie\Activitylog\Models\Concerns\CausesActivity` |
| `Spatie\Activitylog\Traits\HasActivity` | `Spatie\Activitylog\Models\Concerns\HasActivity` |

```bash
# Rewrite the imports across your app
grep -rl 'Spatie\\Activitylog\\Traits' app/ \
  | xargs sed -i '' 's|Spatie\\Activitylog\\Traits\\|Spatie\\Activitylog\\Models\\Concerns\\|g'
```

The relations were renamed too: `$model->activities` is now
`$model->activitiesAsSubject`, and `$model->actions` is now
`$model->activitiesAsCauser`. `getActivitylogOptions()` is optional in v5. See
Spatie's own upgrade guide for the complete rename table.

## Step 2: Update this package

```bash
composer require muhammadsadeeq/laravel-activitylog-ui:"^2.0"
```

### Authorization now defaults to on

If you never published `config/activitylog-ui.php`, the UI previously required no
authentication at all. It now requires a logged-in user who passes the
`viewActivityLogUi` gate, which by default allows any authenticated user.

* Published configs keep whatever value they already contain and are unaffected.
* To keep the old behaviour, set `ACTIVITYLOG_UI_AUTHORIZATION=false`.
* With authorization on, a guest hitting the UI in a browser is redirected by
  Laravel's `auth` middleware, so your app needs a named `login` route (or a
  `redirectGuestsTo()` callback). JSON requests get a `401` instead.
* `access.allowed_users` / `access.allowed_roles` are now enforced even when
  authorization is disabled. They previously had no effect in that combination.

## Step 3: Republish views (if published)

If you previously published views with `vendor:publish --tag=activitylog-ui-views`, you must republish them:

```bash
php artisan vendor:publish --tag=activitylog-ui-views --force
```

Key changes in published views:
- Batch UUID column, filter, and JS helpers removed
- Timeline and detail modal now reference `activity.attribute_changes` instead of `activity.properties.old` / `activity.properties.attributes`

## Step 4: Update custom code

### `hasPropertyChanges()` is deprecated

If you call `hasPropertyChanges()` in custom code, migrate to `hasAttributeChanges()`:

```php
// Before
$activity->hasPropertyChanges();

// After
$activity->hasAttributeChanges();
```

### Properties column meaning changed

In v1 (Spatie v4), `properties` contained both tracked attribute changes and custom data. In v2 (Spatie v5):
- `attribute_changes` — tracked model field changes (`old` / `attributes`)
- `properties` — only custom data set via `withProperties()`

### Batch UUID removed

The batch UUID filter and display have been removed. If you used batch grouping, Spatie v5 recommends using custom properties:

```php
activity()->withProperty('group', $groupId)->log('...');
```
