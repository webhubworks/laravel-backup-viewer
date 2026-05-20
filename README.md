# Laravel Backup Viewer

> A read-only admin page for [`spatie/laravel-backup`](https://github.com/spatie/laravel-backup).
> No interactive scheduling, no setting changes — just a clean view of the state your backup config produces.

## Features

- **Backup health** — last run, last successful run, last monitor run, scheduled commands (with humanized cron timings)
- **Per-target checks** — one section per disk × backup-name; reachability, configured spatie checks, plus a synthetic free-disk-space check on local disks
- **Backups by target** — file table per disk, with size / created / download for local-disk backups, encryption-state badges, and a disk-usage bar
- **Notifications** — event → channel → recipient routing for every entry in `backup.notifications.notifications`

Event-driven: the page reads from a small JSON state file populated by listeners that subscribe to spatie's `BackupHasFailed`, `BackupWasSuccessful`, `HealthyBackupWasFound`, `UnhealthyBackupWasFound`. No work runs on page load except free-disk-space probing.

## Installation

```bash
composer require webhubworks/laravel-backup-viewer
```

The package depends on `spatie/laravel-backup` to actually do anything useful — install it too if you haven't:

```bash
composer require spatie/laravel-backup
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag=backup-config
```

## Authorization

The route is **only accessible in the `local` environment by default**. Open it up elsewhere with the gate-style API (same pattern as `Horizon::auth`):

```php
// app/Providers/AppServiceProvider.php
use Webhub\BackupViewer\BackupViewer;

public function boot(): void
{
    BackupViewer::auth(function ($request) {
        return $request->user()?->is_admin === true;
    });
}
```

The callback receives the incoming `Illuminate\Http\Request` and must return `true` to allow access.

## Configuration

Default settings work for most apps. To override (route path, middleware, download size cap, favicon, low-disk threshold), publish the config:

```bash
php artisan vendor:publish --tag=backup-viewer-config
```

That writes `config/backup-viewer.php`:

```php
return [
    'enabled' => true,
    'route' => [
        'path' => 'backups',
        'name' => 'backup-viewer.index',
        'domain' => null,
    ],
    'middleware' => ['web'],
    'download' => [
        'max_bytes' => 500 * 1024 * 1024, // 500 MB cap; null to disable
    ],
    'low_disk_space_threshold' => 0.15, // warn when < 15% free
    'monitor_stale_after_minutes' => 1440, // 24h
    'favicon' => [
        'html' => null, // raw <link ...> block pasted from your main layout
        'path' => null, // OR a single href like '/favicon.svg'
    ],
];
```

### Multi-icon favicon setups

For apps that use [realfavicongenerator](https://realfavicongenerator.net)-style multi-icon setups, paste the entire `<link>` block into the `favicon.html` key:

```php
'favicon' => [
    'html' => <<<'HTML'
        <link rel="apple-touch-icon" sizes="180x180" href="/meta/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/meta/favicon-32x32.png">
        <link rel="manifest" href="/meta/site.webmanifest">
    HTML,
    'path' => null,
],
```

## Where state is stored

Listeners write event activity to `<first-local-backup-disk>/<backup-name>/laravel-backup-viewer-state.json` so the file lives next to the backups it describes. Apps with only remote disks fall back to `storage/app/backup-viewer/state.json`. The file is written atomically (tmp + rename).

## Scheduling

Stick the spatie commands in Laravel's scheduler:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:clean')->daily()->at('01:00')->onOneServer();
Schedule::command('backup:run --only-db')->daily()->at('02:00')->onOneServer();
Schedule::command('backup:run --only-files')->daily()->at('03:00')->onOneServer();
Schedule::command('backup:monitor')->daily()->at('04:00')->onOneServer();
```

The Backup health card surfaces those entries and humanizes the cron expression ("Daily at 02:00").

## Commands you'll actually run

This package adds no Artisan commands of its own. The relevant ones come from `spatie/laravel-backup`:

| Command | Purpose |
| --- | --- |
| `php artisan backup:run` | Create a new backup |
| `php artisan backup:run --only-db` | Database-only backup |
| `php artisan backup:list` | List backups across all configured disks |
| `php artisan backup:clean` | Apply retention rules |
| `php artisan backup:monitor` | Re-run health checks; populates this page's monitor card |

## Frontend

Pre-compiled Tailwind v4 + Alpine.js are committed inside the package and inlined into the response via `BackupViewer::css()` / `BackupViewer::js()` (same pattern as Laravel Horizon). The host app needs no Vite config, no `vendor:publish` step for assets.

## Compatibility

- PHP 8.3+
- Laravel 11, 12, 13
- spatie/laravel-backup 9.x

## License

MIT. See [LICENSE.md](LICENSE.md).
