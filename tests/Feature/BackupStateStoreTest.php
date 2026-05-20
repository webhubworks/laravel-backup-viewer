<?php

use Illuminate\Support\Facades\File;
use Webhub\BackupViewer\Services\BackupStateStore;

beforeEach(function (): void {
    // Point spatie's backup config at a local disk rooted in a temp dir so the
    // state store has somewhere predictable to write.
    $this->tmpDir = sys_get_temp_dir().'/ls-backup-state-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->tmpDir);

    config()->set('filesystems.disks.test-local', [
        'driver' => 'local',
        'root' => $this->tmpDir,
    ]);
    config()->set('backup.backup.destination.disks', ['test-local']);
    config()->set('backup.backup.name', 'fixture-app');
});

afterEach(function (): void {
    if (isset($this->tmpDir) && is_dir($this->tmpDir)) {
        File::deleteDirectory($this->tmpDir);
    }
});

it('returns an empty shape when the state file is missing', function (): void {
    $state = (new BackupStateStore)->read();

    expect($state)->toMatchArray([
        'lastRun' => null,
        'lastSuccessfulRun' => null,
        'lastMonitor' => null,
    ]);
});

it('writes the state file under the configured local backup disk', function (): void {
    $store = new BackupStateStore;
    $store->recordBackupSuccess('test-local', 'fixture-app', 1_700_000_000);

    $expected = $this->tmpDir.'/fixture-app/'.BackupStateStore::FILENAME;
    expect($store->absolutePath())->toBe($expected);
    expect(is_file($expected))->toBeTrue();
});

it('records a successful run as lastRun and lastSuccessfulRun', function (): void {
    $store = new BackupStateStore;
    $store->recordBackupSuccess('test-local', 'fixture-app', 1_700_000_000);

    $state = $store->read();

    expect($state['lastRun'])->toMatchArray([
        'at' => 1_700_000_000,
        'status' => 'ok',
        'diskName' => 'test-local',
        'backupName' => 'fixture-app',
        'errors' => [],
    ]);
    expect($state['lastSuccessfulRun'])->toMatchArray([
        'at' => 1_700_000_000,
        'diskName' => 'test-local',
    ]);
});

it('records a failed run without touching lastSuccessfulRun', function (): void {
    $store = new BackupStateStore;
    $store->recordBackupSuccess('test-local', 'fixture-app', 1_700_000_000);
    $store->recordBackupFailure('test-local', 'fixture-app', 'disk full', 1_700_000_500);

    $state = $store->read();

    expect($state['lastRun']['status'])->toBe('failed');
    expect($state['lastRun']['errors'])->toBe(['disk full']);
    expect($state['lastSuccessfulRun']['at'])->toBe(1_700_000_000);
});

it('upserts monitor results keyed by disk|name', function (): void {
    $store = new BackupStateStore;
    $store->recordMonitorResult('test-local', 'fixture-app', [
        'isHealthy' => true,
        'amountOfBackups' => 5,
        'newestBackupAt' => 1_700_000_000,
        'usedStorageBytes' => 1234,
        'failures' => [],
    ], 1_700_000_100);

    $state = $store->read();
    expect($state['lastMonitor']['at'])->toBe(1_700_000_100);
    expect($state['lastMonitor']['destinations']['test-local|fixture-app'])->toMatchArray([
        'isHealthy' => true,
        'amountOfBackups' => 5,
    ]);

    // Second call replaces the entry under the same key.
    $store->recordMonitorResult('test-local', 'fixture-app', [
        'isHealthy' => false,
        'amountOfBackups' => 5,
        'newestBackupAt' => 1_700_000_000,
        'usedStorageBytes' => 1234,
        'failures' => [['check' => 'Is Reachable', 'message' => 'nope']],
    ], 1_700_000_200);

    $state = $store->read();
    expect($state['lastMonitor']['at'])->toBe(1_700_000_200);
    expect($state['lastMonitor']['destinations']['test-local|fixture-app']['isHealthy'])->toBeFalse();
    expect($state['lastMonitor']['destinations']['test-local|fixture-app']['failures'])
        ->toBe([['check' => 'Is Reachable', 'message' => 'nope']]);
});

it('survives a corrupt state file by returning the empty shape', function (): void {
    $store = new BackupStateStore;
    File::ensureDirectoryExists(dirname($store->absolutePath()));
    file_put_contents($store->absolutePath(), '{not valid json');

    expect($store->read())->toMatchArray([
        'lastRun' => null,
        'lastSuccessfulRun' => null,
        'lastMonitor' => null,
    ]);
});
