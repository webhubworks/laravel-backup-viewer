<?php

namespace Webhub\BackupViewer\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Webhub\BackupViewer\BackupViewerServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BackupViewerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
    }
}
