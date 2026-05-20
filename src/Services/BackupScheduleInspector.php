<?php

namespace Webhub\BackupViewer\Services;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Throwable;

/**
 * Reads Laravel's Schedule container and surfaces entries for the spatie
 * commands we care about (backup:run, backup:monitor). Falls back to the
 * raw cron expression when the pattern isn't one of the common ones the
 * humanizer recognizes.
 *
 * Schedules in modern Laravel apps live in routes/console.php which is
 * normally only loaded during console kernel bootstrapping. We require
 * it once on demand so the page can read the schedule from a web request.
 */
class BackupScheduleInspector
{
    private const TRACKED_COMMANDS = ['backup:run', 'backup:monitor'];

    /**
     * @return array<int, array{command: string, fullCommand: string, cron: string, human: string, timezone: ?string}>
     */
    public function find(): array
    {
        $this->ensureScheduleLoaded();

        try {
            $schedule = app(Schedule::class);
        } catch (Throwable) {
            return [];
        }

        $matches = [];
        foreach ($schedule->events() as $event) {
            if (! $event instanceof Event) {
                continue;
            }

            $detected = $this->detectCommand((string) ($event->command ?? ''));
            if ($detected === null) {
                continue;
            }

            $matches[] = [
                'command' => $detected['name'],
                'fullCommand' => $detected['full'],
                'cron' => (string) $event->expression,
                'human' => $this->humanize((string) $event->expression),
                'timezone' => $event->timezone instanceof \DateTimeZone
                    ? $event->timezone->getName()
                    : (is_string($event->timezone) ? $event->timezone : null),
            ];
        }

        return $matches;
    }

    /**
     * Convert a 5-field cron expression to a human-readable phrase for
     * common cases. Returns the raw expression when no pattern matches.
     */
    public function humanize(string $cron): string
    {
        $cron = trim($cron);
        if ($cron === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $cron) ?: [];
        if (count($parts) !== 5) {
            return $cron;
        }

        [$min, $hour, $dom, $month, $dow] = $parts;
        $restIsWildcard = $dom === '*' && $month === '*' && $dow === '*';

        if ($cron === '* * * * *') {
            return 'Every minute';
        }

        if ($restIsWildcard && $hour === '*' && preg_match('/^\*\/(\d+)$/', $min, $m) === 1) {
            return "Every {$m[1]} minutes";
        }

        if ($restIsWildcard && $hour === '*' && $min === '0') {
            return 'Hourly';
        }

        if ($restIsWildcard && $hour === '*' && ctype_digit($min)) {
            return 'Hourly at :'.str_pad($min, 2, '0', STR_PAD_LEFT);
        }

        if ($restIsWildcard && ctype_digit($min) && ctype_digit($hour)) {
            return 'Daily at '.$this->timeOfDay($hour, $min);
        }

        if ($restIsWildcard && ctype_digit($min) && preg_match('/^\d+(,\d+)+$/', $hour) === 1) {
            $times = array_map(fn ($h) => $this->timeOfDay($h, $min), explode(',', $hour));

            return 'Daily at '.implode(', ', $times);
        }

        if ($dom === '*' && $month === '*' && ctype_digit($min) && ctype_digit($hour) && ctype_digit($dow)) {
            return 'Weekly on '.$this->dayName($dow).' at '.$this->timeOfDay($hour, $min);
        }

        return $cron;
    }

    /**
     * Look for a tracked command name inside the shell command line built
     * by Laravel and return both the bare name and the full
     * artisan-style invocation (command + args, stripped of php binary
     * path, artisan path, and shell redirects).
     *
     * @return array{name: string, full: string}|null
     */
    private function detectCommand(string $command): ?array
    {
        foreach (self::TRACKED_COMMANDS as $needle) {
            $quoted = preg_quote($needle, '/');
            // Match the command name plus all subsequent tokens until we
            // hit a shell metachar (redirect/pipe/background) or EOL.
            if (preg_match("/\\b{$quoted}\\b[^>|&]*/", $command, $m) === 1) {
                return [
                    'name' => $needle,
                    'full' => trim($m[0]),
                ];
            }
        }

        return null;
    }

    /**
     * Routes/console.php (Laravel 11+) and App\Console\Kernel::schedule()
     * (legacy) are normally only invoked by the console kernel. Pull them
     * in from a web request so the page can show what's scheduled.
     */
    private function ensureScheduleLoaded(): void
    {
        $path = base_path('routes/console.php');
        if (is_file($path)) {
            try {
                require_once $path;
            } catch (Throwable) {
                // best-effort: ignore parse/runtime errors from the host app
            }
        }

        if (class_exists('App\\Console\\Kernel')) {
            try {
                $kernel = app('Illuminate\\Contracts\\Console\\Kernel');
                if (method_exists($kernel, 'bootstrap')) {
                    $kernel->bootstrap();
                }
            } catch (Throwable) {
                // ignore
            }
        }
    }

    private function timeOfDay(string $hour, string $min): string
    {
        return str_pad($hour, 2, '0', STR_PAD_LEFT).':'.str_pad($min, 2, '0', STR_PAD_LEFT);
    }

    private function dayName(string $dow): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return $days[(int) $dow] ?? "day {$dow}";
    }
}
