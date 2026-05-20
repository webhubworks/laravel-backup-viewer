<?php

namespace Webhub\BackupViewer\Services;

use Illuminate\Support\Str;

/**
 * Surfaces what spatie/laravel-backup is configured to notify about and
 * via which channels, so the /backups page can show the routing table
 * without operators digging through config/backup.php.
 *
 * Only reads from config; never sends anything.
 */
class BackupNotificationsInspector
{
    /**
     * @return array<int, array{
     *     class: class-string,
     *     label: string,
     *     channels: array<int, array{name: string, target: ?string, status: 'configured'|'fallback'|'missing'|'unknown'}>,
     * }>
     */
    public function events(): array
    {
        $configured = (array) config('backup.notifications.notifications', []);

        $events = [];
        foreach ($configured as $class => $channels) {
            if (! is_string($class) || ! is_array($channels)) {
                continue;
            }

            $cleanChannels = [];
            foreach ($channels as $c) {
                if (! is_string($c) || trim($c) === '') {
                    continue;
                }
                $name = trim($c);
                $cleanChannels[] = $this->resolveChannel($name);
            }

            if ($cleanChannels === []) {
                continue;
            }

            $events[] = [
                'class' => $class,
                'label' => $this->humanLabel($class),
                'channels' => $cleanChannels,
            ];
        }

        return $events;
    }

    /**
     * @return array{name: string, target: ?string, status: 'configured'|'fallback'|'missing'|'unknown'}
     */
    private function resolveChannel(string $name): array
    {
        return match ($name) {
            'mail' => $this->mailChannel($name),
            'slack' => $this->slackChannel($name),
            default => ['name' => $name, 'target' => null, 'status' => 'unknown'],
        };
    }

    /**
     * @return array{name:string, target:?string, status:'configured'|'missing'}
     */
    private function mailChannel(string $name): array
    {
        $to = config('backup.notifications.mail.to');

        if (is_string($to) && trim($to) !== '') {
            return ['name' => $name, 'target' => trim($to), 'status' => 'configured'];
        }

        if (is_array($to)) {
            $emails = array_values(array_filter(array_map(
                static fn ($v): ?string => is_string($v) ? trim($v) : null,
                $to,
            ), static fn (?string $v): bool => $v !== null && $v !== ''));

            if ($emails !== []) {
                return ['name' => $name, 'target' => implode(', ', $emails), 'status' => 'configured'];
            }
        }

        return ['name' => $name, 'target' => null, 'status' => 'missing'];
    }

    /**
     * @return array{name:string, target:?string, status:'configured'|'fallback'}
     */
    private function slackChannel(string $name): array
    {
        $channel = config('backup.notifications.slack.channel');

        if (is_string($channel) && trim($channel) !== '') {
            return ['name' => $name, 'target' => trim($channel), 'status' => 'configured'];
        }

        // slack.channel = null is documented to mean "use the webhook's
        // default channel" — that's a fallback, not a misconfiguration.
        return ['name' => $name, 'target' => null, 'status' => 'fallback'];
    }

    /**
     * "Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification"
     *   → "Backup has failed"
     */
    private function humanLabel(string $class): string
    {
        $base = class_basename($class);
        $base = Str::endsWith($base, 'Notification') ? substr($base, 0, -strlen('Notification')) : $base;

        return Str::ucfirst(strtolower(Str::snake($base, ' ')));
    }
}
