<?php

use Webhub\BackupViewer\Services\BackupScheduleInspector;

it('humanizes common cron expressions', function (string $cron, string $expected): void {
    expect((new BackupScheduleInspector)->humanize($cron))->toBe($expected);
})->with([
    'every minute' => ['* * * * *', 'Every minute'],
    'every 5 minutes' => ['*/5 * * * *', 'Every 5 minutes'],
    'every 10 minutes' => ['*/10 * * * *', 'Every 10 minutes'],
    'hourly on the hour' => ['0 * * * *', 'Hourly'],
    'hourly at quarter past' => ['15 * * * *', 'Hourly at :15'],
    'daily midnight' => ['0 0 * * *', 'Daily at 00:00'],
    'daily at 02:30' => ['30 2 * * *', 'Daily at 02:30'],
    'twice daily' => ['0 1,13 * * *', 'Daily at 01:00, 13:00'],
    'three times daily' => ['0 6,12,18 * * *', 'Daily at 06:00, 12:00, 18:00'],
    'weekly sunday midnight' => ['0 0 * * 0', 'Weekly on Sunday at 00:00'],
    'weekly monday morning' => ['30 9 * * 1', 'Weekly on Monday at 09:30'],
    'unrecognized falls back to raw' => ['1-5 0 1 1 *', '1-5 0 1 1 *'],
    'blank passes through' => ['', ''],
]);
