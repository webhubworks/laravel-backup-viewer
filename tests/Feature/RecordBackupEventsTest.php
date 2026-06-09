<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\HealthCheck;
use Webhub\BackupViewer\Listeners\RecordBackupEvents;
use Webhub\BackupViewer\Services\BackupStateStore;

beforeEach(function (): void {
    $this->tmpDir = sys_get_temp_dir().'/ls-backup-events-'.bin2hex(random_bytes(4));
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

function fixtureDestination(): BackupDestination
{
    return BackupDestination::create('test-local', 'fixture-app');
}

it('records a successful backup from the spatie event', function (): void {
    event(new BackupWasSuccessful(fixtureDestination()));

    $state = (new BackupStateStore)->read();

    expect($state['lastRun'])->toMatchArray([
        'status' => 'ok',
        'diskName' => 'test-local',
        'backupName' => 'fixture-app',
    ]);
    expect($state['lastSuccessfulRun']['diskName'])->toBe('test-local');
});

it('records a failed backup from the spatie event', function (): void {
    event(new BackupHasFailed(new Exception('disk full'), fixtureDestination()));

    $state = (new BackupStateStore)->read();

    expect($state['lastRun']['status'])->toBe('failed');
    expect($state['lastRun']['errors'])->toBe(['disk full']);
});

it('records a failed backup even when no destination is attached', function (): void {
    event(new BackupHasFailed(new Exception('could not connect')));

    $state = (new BackupStateStore)->read();

    expect($state['lastRun']['status'])->toBe('failed');
    expect($state['lastRun']['errors'])->toBe(['could not connect']);
});

it('records a healthy monitor result from the spatie event', function (): void {
    event(new HealthyBackupWasFound(new BackupDestinationStatus(fixtureDestination())));

    $state = (new BackupStateStore)->read();
    $entry = $state['lastMonitor']['destinations']['test-local|fixture-app'];

    expect($entry['isHealthy'])->toBeTrue();
    expect($entry['failures'])->toBe([]);
});

it('records an unhealthy monitor result with the failing check from the spatie event', function (): void {
    $failingCheck = new class extends HealthCheck
    {
        public function checkHealth(BackupDestination $backupDestination): void
        {
            $this->fail('backup is too old');
        }
    };

    $status = new BackupDestinationStatus(fixtureDestination(), [$failingCheck]);
    $status->isHealthy();

    event(new UnhealthyBackupWasFound($status));

    $state = (new BackupStateStore)->read();
    $entry = $state['lastMonitor']['destinations']['test-local|fixture-app'];

    expect($entry['isHealthy'])->toBeFalse();
    expect($entry['failures'])->toHaveCount(1);
    expect($entry['failures'][0]['message'])->toBe('backup is too old');
    expect($entry['failures'][0]['check'])->toBeString()->not->toBeEmpty();
});

/**
 * The installed spatie/laravel-backup is v9, whose events are object-based.
 * v10 instead exposes diskName/backupName as plain strings (and unhealthy
 * failures as a Collection). These stubs mirror the v10 payload shape so the
 * listener's v10 branch is exercised without installing v10 itself.
 */
class V10BackupWasSuccessful extends BackupWasSuccessful
{
    public function __construct(public string $diskName, public string $backupName) {}
}

class V10BackupHasFailed extends BackupHasFailed
{
    public function __construct(Exception $exception, public ?string $diskName = null, public ?string $backupName = null)
    {
        parent::__construct($exception);
    }
}

class V10HealthyBackupWasFound extends HealthyBackupWasFound
{
    public function __construct(public string $diskName, public string $backupName) {}
}

class V10UnhealthyBackupWasFound extends UnhealthyBackupWasFound
{
    public function __construct(
        public string $diskName,
        public string $backupName,
        public Collection $failureMessages,
    ) {}
}

it('records a successful backup from the v10 string-based event', function (): void {
    app(RecordBackupEvents::class)->onBackupSuccess(new V10BackupWasSuccessful('test-local', 'fixture-app'));

    $state = (new BackupStateStore)->read();

    expect($state['lastRun'])->toMatchArray([
        'status' => 'ok',
        'diskName' => 'test-local',
        'backupName' => 'fixture-app',
    ]);
});

it('records a failed backup from the v10 string-based event', function (): void {
    app(RecordBackupEvents::class)->onBackupFailure(new V10BackupHasFailed(new Exception('disk full'), 'test-local', 'fixture-app'));

    $state = (new BackupStateStore)->read();

    expect($state['lastRun']['status'])->toBe('failed');
    expect($state['lastRun']['errors'])->toBe(['disk full']);
});

it('records a healthy monitor result from the v10 string-based event', function (): void {
    app(RecordBackupEvents::class)->onHealthyDestination(new V10HealthyBackupWasFound('test-local', 'fixture-app'));

    $state = (new BackupStateStore)->read();
    $entry = $state['lastMonitor']['destinations']['test-local|fixture-app'];

    expect($entry['isHealthy'])->toBeTrue();
    expect($entry['failures'])->toBe([]);
});

it('records an unhealthy monitor result from the v10 collection-based event', function (): void {
    $event = new V10UnhealthyBackupWasFound('test-local', 'fixture-app', collect([
        ['check' => 'Maximum age', 'message' => 'backup is too old'],
    ]));

    app(RecordBackupEvents::class)->onUnhealthyDestination($event);

    $state = (new BackupStateStore)->read();
    $entry = $state['lastMonitor']['destinations']['test-local|fixture-app'];

    expect($entry['isHealthy'])->toBeFalse();
    expect($entry['failures'])->toBe([
        ['check' => 'Maximum age', 'message' => 'backup is too old'],
    ]);
});
