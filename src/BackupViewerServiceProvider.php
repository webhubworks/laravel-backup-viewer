<?php

namespace Webhub\BackupViewer;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Spatie\Backup\BackupServiceProvider as SpatieBackupServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Webhub\BackupViewer\Http\Middleware\Authorize as BackupAuthorize;
use Webhub\BackupViewer\Listeners\RecordBackupEvents;

class BackupViewerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('backup-viewer')
            ->hasConfigFile('backup-viewer')
            ->hasViews('backup-viewer');
    }

    public function packageBooted(): void
    {
        $this->registerRoutes();
        $this->registerConfigPublishAlias();
        $this->registerBackupEventSubscriber();
    }

    private function registerRoutes(): void
    {
        if (! config('backup-viewer.enabled', true)) {
            return;
        }

        $routeConfig = (array) config('backup-viewer.route', []);
        $middleware = (array) config('backup-viewer.middleware', ['web']);

        Route::group([
            'domain' => $routeConfig['domain'] ?? null,
            'prefix' => $routeConfig['path'] ?? 'backups',
            'middleware' => array_merge($middleware, [BackupAuthorize::class]),
            'as' => ($routeConfig['name'] ?? 'backup-viewer.index'),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    private function registerConfigPublishAlias(): void
    {
        // spatie/laravel-package-tools auto-registers a publish tag named
        // "backup-viewer-config" via hasConfigFile() — same as the prefixed
        // name we want. Nothing else to do.
    }

    private function registerBackupEventSubscriber(): void
    {
        // Only wire the subscriber when spatie/laravel-backup is installed —
        // its event classes are not autoloadable otherwise.
        if (! class_exists(SpatieBackupServiceProvider::class)) {
            return;
        }

        $this->app->afterResolving(Dispatcher::class, function (Dispatcher $events): void {
            $events->subscribe(RecordBackupEvents::class);
        });

        if ($this->app->resolved(Dispatcher::class)) {
            $this->app->make(Dispatcher::class)->subscribe(RecordBackupEvents::class);
        }
    }
}
