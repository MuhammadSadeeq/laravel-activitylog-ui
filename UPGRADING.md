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
| `Spatie\Activitylog\Traits\HasActivity` | `Spatie\Activitylog\Models\Concerns\HasActivity` (v5 reintroduces this trait; it did not exist in v4) |
| `Spatie\Activitylog\LogOptions` | `Spatie\Activitylog\Support\LogOptions` |

The `LogOptions` move matters as much as the traits: any model with a
`getActivitylogOptions()` method imports it, and the old class is gone, so the
first logged event fails.

```bash
# Rewrite the imports across your app. Uses perl rather than sed because the
# in-place flag differs between GNU and BSD, and find -exec rather than a
# grep/xargs pipeline so filenames containing spaces are handled.
find app -name '*.php' -exec perl -pi -e '
    s{Spatie\\Activitylog\\Traits\\}{Spatie\\Activitylog\\Models\\Concerns\\}g;
    s{Spatie\\Activitylog\\LogOptions}{Spatie\\Activitylog\\Support\\LogOptions}g;
' {} +

# Check nothing was missed
grep -rn 'Activitylog\\Traits\|Activitylog\\LogOptions' app/ || echo "all imports updated"
```

Some relations were renamed too: `$model->activities` becomes
`$model->activitiesAsSubject` and `$model->actions` becomes
`$model->activitiesAsCauser`. (The `HasActivity` trait keeps `activities()` as an
alias, so models using that trait are unaffected.) `getActivitylogOptions()` is
optional in v5. See Spatie's own upgrade guide for the complete rename table.

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

### Out-of-range and unusable parameters are now refused

The API used to quietly answer a different question than the one it was asked.
`per_page=999999` became 100, `per_page=-5` became 1, a `causer_id` it could not
use became no causer filter at all, and the 101st `event_types` entry was
dropped. Every one of those returned 200 with no indication that anything had
changed.

For a page whose purpose is to report what happened, the filter cases are the
serious ones: dropping a filter shows **more** of the audit log than was asked
for, and looks like a complete answer.

These now return **422** with a message naming the parameter:

```json
{
  "message": "The per_page parameter must be between 1 and 100. You asked for 999999.",
  "errors": { "per_page": ["The per_page parameter must be between 1 and 100. You asked for 999999."] }
}
```

Affected: `page`, `per_page`, `anchor_id`, `causer_id`, `subject_id`,
`event_types`, and the single-value filters (`search`, `date_preset`,
`start_date`, `end_date`, `causer_type`, `subject_type`, `property_key`). The
export endpoint applies the same rules to the filters in its JSON body.

Omitting a parameter, or sending it empty, still uses the default — only a value
that cannot be honoured is refused. The package's own configured defaults are
still clamped rather than refused, since a `default_per_page` above the cap is
the installation's mistake, not the caller's.

**What to check before upgrading:**

- Bookmarks, scripts or saved views carrying a `per_page` above your largest
  `ui.per_page_options` value. They will now fail instead of silently returning
  fewer rows.
- Any integration passing identifiers that are not integers, UUIDs, ULIDs, or
  `[A-Za-z0-9_-]{1,64}`.
- Requests filtering on more than 100 event types at once.

The dashboard itself surfaces the message in a notification that stays until
dismissed, so a stale filter in a user's browser storage says what is wrong
rather than failing silently.

### The pagination anchor is now a pair

The activity list endpoint pins later pages to the rows that existed when page
one was read, so activities recorded while someone reads do not push the list
down and make page two repeat page one.

That anchor used to be `anchor_id` alone. It is now `anchor_id` **and**
`anchor_time` together, because the listing is ordered by `created_at` with the
key only as a tiebreak, and a predicate has to match that ordering to mean
"everything that existed then". Filtering on the key alone hid rows whenever the
two disagreed — which is any log containing backdated or imported activities.

* The shipped UI handles this itself; nothing to do if you use it.
* If you call `/api/activities` directly, send both values back, taken from
  `anchor_id` and `anchor_time` in the previous response. Sending one without
  the other returns **422**.
* `anchor_id` must now be an integer when the log's key is an integer. It was
  previously accepted as any identifier-shaped string, which MySQL coerced to 0
  and answered with an empty page.
* `ActivitylogService::getActivities()` takes `?array $anchor` as its third
  argument instead of `int|string|null $anchorId`.
* `Activity::hasMonotonicKey()` is gone. It disabled anchoring for UUID and ULID
  keys; the pair comparison is correct for those, so nothing needs to ask.

### Removed: `searchWithSuggestions()`

`ActivitylogService::searchWithSuggestions()` and the `search()` controller
action have been removed. The action had no route, so nothing could reach it
over HTTP, but it duplicated `getSearchSuggestions()` while publishing causer
email addresses regardless of `filters.expose_causer_email`.

Call `getSearchSuggestions(string $query): Collection` instead. It returns the
same `value` / `label` / `type` rows the `/api/search/suggestions` endpoint
serves.

## Step 5: Add the UI's indexes (recommended)

Spatie indexes the activity log for lookups by subject and causer. This UI asks
different questions — it lists everything newest first, and groups by event over
a date range — and neither was indexed. On a log of 200,000 rows MySQL answered
a single page by reading every row and sorting the lot.

```bash
php artisan vendor:publish --tag="activitylog-ui-migrations"
php artisan migrate
```

Measured on 204,963 activities: the first page goes from 350ms to 21ms, and the
analytics queries read an index instead of the table.

This is a separate, opt-in step for two reasons. `activity_log` belongs to
Spatie, not to this package. And building an index on an established log locks
it while it runs — about a second at that size, longer on a bigger one — which
is a decision for whoever runs the database, not for a `composer update`.

The migration reads the table, connection and key name from your configured
activity model, and skips any index that already exists, so it is safe to re-run.
