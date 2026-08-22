# Laravel Activity Log UI

> Beautiful, modern UI for [Spatie's Activity Log](https://github.com/spatie/laravel-activitylog)
>
> **Important:** This package **assumes you already have Spatie's Activity Log installed and configured** in your Laravel application.  It does *not* replace the logging package—only provides a powerful UI for viewing and analyzing the stored activities.

![Activity Log UI Screenshot](laravel-activitylog-ui-screenshot.png)

---

## 📖 Documentation

📚 **[Complete Documentation](https://www.sadeeq.dev/docs/laravel-activitylog-ui)** - Comprehensive guide with advanced features, customization options, and troubleshooting.

---

## ⬆️ Upgrading from v1.x

v3.0 is a breaking release: the UI now requires authentication by default, exports are scoped to whoever created them, and unusable parameters are refused rather than quietly reinterpreted. v2.0 before it moved to Spatie laravel-activitylog v5. See **[UPGRADING.md](UPGRADING.md)** for both paths.

---

## ✨ Features

* Table, Timeline & Analytics dashboards
* Powerful filter panel (date presets, events, users, subjects, search)
* Saved views, per-page & sorting preferences
* Export to **CSV / Excel / PDF / JSON**  
  \* Optional Excel & PDF exports require additional packages (see below)
* Real-time count & pagination powered by Laravel cache
* Authorization gate, middleware & granular access lists
* Tailwind CSS & Alpine.js – no build step required

## 🗒️ Requirements

* PHP ≥ 8.4
* Laravel 12 | 13
* [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) ≥ 5.0 (already logging your activities)
* Database table `activity_log` with Spatie v5’s schema (includes `attribute_changes` column)

> **On Laravel 12** Composer resolves `spatie/laravel-activitylog` to **5.0.0**, because 5.1.0 requires `illuminate/* ^13.0`. The schema and everything in this package behave the same on both; you are simply pinned to 5.0.x until you move to Laravel 13. Verified against Laravel 12.66 / Spatie 5.0.0 and Laravel 13.25 / Spatie 5.1.0.

### Optional (for export)

| Feature | Package | Version |
|---------|---------|---------|
| Excel (XLSX) | `maatwebsite/excel` | ^3.1 |
| PDF | `barryvdh/laravel-dompdf` | ^2.0 |

Add them when you need those formats:
```bash
composer require maatwebsite/excel barryvdh/laravel-dompdf
```

---

## 🚀 Installation

1. **Install the package**
   ```bash
   composer require muhammadsadeeq/laravel-activitylog-ui
   ```
2. **(Optional) Publish resources**
   ```bash
   # Config file (config/activitylog-ui.php)
   php artisan vendor:publish --provider="MuhammadSadeeq\ActivitylogUi\ActivitylogUiServiceProvider" --tag="activitylog-ui-config"

   # Blade views (if you want to customise)
   php artisan vendor:publish --provider="MuhammadSadeeq\ActivitylogUi\ActivitylogUiServiceProvider" --tag="activitylog-ui-views"

   # Public assets (logo, js, css)
   php artisan vendor:publish --provider="MuhammadSadeeq\ActivitylogUi\ActivitylogUiServiceProvider" --tag="activitylog-ui-assets"
   ```
3. **Run migrations**   
   Ensure you have already run Spatie’s migrations so the `activity_log` table exists:
   ```bash
   php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
   php artisan migrate
   ```
4. **(Recommended on a large log) Add the UI’s indexes**   
   Spatie indexes the log for lookups by subject and causer. This UI lists
   everything newest first, which nothing indexes — on a log of 200,000 rows
   MySQL answers a single page by reading every row and sorting the lot.
   ```bash
   php artisan vendor:publish --provider="MuhammadSadeeq\ActivitylogUi\ActivitylogUiServiceProvider" --tag="activitylog-ui-migrations"
   php artisan migrate
   ```
   Measured on 204,963 activities, this took the first page from 350&nbsp;ms to
   21&nbsp;ms and let the analytics queries read an index instead of the table.
   It is a separate step because `activity_log` is Spatie’s table and because
   building an index on an established log locks it while it runs — about a
   second at that size, longer on a bigger one.
5. **Visit the UI**   
   ```
   /activitylog-ui   # default route prefix
   ```

---

## ⚙️ Configuration Overview

A full configuration file is published to `config/activitylog-ui.php`.  Below is a quick reference:

```php
return [
    'route' => [
        'prefix' => 'activitylog-ui', // URL prefix
        'middleware' => null,         // Auto-detected or custom array
    ],

    'authorization' => [
        'enabled' => true,            // false => the UI is fully public
        'gate'    => 'viewActivityLogUi',
    ],

    'access' => [
        'allowed_users' => [],        // user email whitelist
        'allowed_roles' => [],        // role names (Spatie Permission, etc.)
    ],

    'features' => [
        'analytics' => true,
        'exports'   => true,
        'saved_views' => true,
    ],

    'exports' => [
        'enabled_formats' => ['csv', 'xlsx', 'pdf', 'json'],
        'max_records'     => 10000,
        'queue' => [
            'enabled'   => false,
            'threshold' => 1000,
            'queue_name'=> 'exports',
        ],
    ],
];
```
Refer to the inline comments in the file for every available option.

---

## 🗄️ Custom Table or Connection

Spatie v5 removed the `activitylog.table_name` and `activitylog.database_connection`
settings. The supported way to move the log is a custom Activity model:

```php
use Spatie\Activitylog\Models\Activity as BaseActivity;

class Activity extends BaseActivity
{
    protected $table = 'my_activity_log';
    protected $connection = 'my_connection';
}
```

```php
// config/activitylog.php
'activity_model' => \App\Models\Activity::class,
```

This UI reads that model's table and connection, so it follows the log wherever
you put it — no additional configuration here. Note it reads the *location*, not
the model itself: scopes, casts and accessors you add to your model are not used
by the UI's queries.

---

## 🔐 Authorization & Access Control

Access is decided in this order:

1. **`authorization.enabled`** (default `true`, or `ACTIVITYLOG_UI_AUTHORIZATION`) — requires a logged-in user, then the gate below. Turning it off removes the login requirement.
2. **`access.allowed_users` / `access.allowed_roles`** — if either is non-empty it is enforced **regardless of step 1**, so an allow-list still requires a matching logged-in user even with authorization disabled.
3. With authorization off **and** both lists empty, the UI is fully public: anyone who can reach the URL can read who did what, when, and to which record. That combination is for local development only.

Notes:

* **Gate:** `viewActivityLogUi` is auto-registered (see `ActivitylogUiServiceProvider`). By default it allows any authenticated user and narrows to the lists above once you set them. Define your own to replace that logic.
* **Roles** use `hasAnyRole()` or `hasRole()` if your user model provides them (Spatie Permission and similar). Without either method, a user cannot match a role and is denied.
* **`route.middleware`** replaces the base stack (`['web']`) only. Authentication and the access middleware are appended afterwards and cannot be removed by it.
* **Requires a named `login` route** when authorization is on and a guest opens the UI in a browser — that is Laravel's `auth` middleware redirect. Apps without auth scaffolding should configure `redirectGuestsTo()` or leave authorization off. JSON requests get a `401` instead.

---

## 📤 Exports

* **CSV & JSON** work out-of-the-box.
* **Excel (XLSX)** requires `maatwebsite/excel` – otherwise we gracefully fall back to CSV.
* **PDF** requires `barryvdh/laravel-dompdf` – otherwise we fall back to JSON.
* Large exports can be **queued**; enable `exports.queue.enabled`.

---

## 📈 Analytics Dashboard

Enable/disable with `features.analytics`.  Caches stats for `analytics.cache_duration` seconds (default 1 h).

---

## 🤝 Contributing

PRs and issues are welcome!

---

## 📝 License

The MIT License (MIT).  See `LICENSE` for details. 

[![Latest Version on Packagist](https://img.shields.io/packagist/v/muhammadsadeeq/laravel-activitylog-ui.svg?style=flat-square)](https://packagist.org/packages/muhammadsadeeq/laravel-activitylog-ui)
[![Total Downloads](https://img.shields.io/packagist/dt/muhammadsadeeq/laravel-activitylog-ui.svg?style=flat-square)](https://packagist.org/packages/muhammadsadeeq/laravel-activitylog-ui)
