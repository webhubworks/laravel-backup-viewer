<?php

namespace Webhub\BackupViewer\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DownloadBackupController
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'targetName' => ['required', 'string'],
            'backupName' => ['required', 'string'],
        ]);

        $targetName = $data['targetName'];
        $backupName = $data['backupName'];

        $configuredDisks = (array) config('backup.backup.destination.disks', []);
        if (! in_array($targetName, $configuredDisks, true)) {
            throw new BadRequestHttpException('Unknown backup target.');
        }

        if (config("filesystems.disks.{$targetName}.driver") !== 'local') {
            throw new BadRequestHttpException('Downloads are only supported for local targets.');
        }

        if (! preg_match('/^[A-Za-z0-9._-]+\.zip$/', $backupName)) {
            throw new BadRequestHttpException('Invalid backup filename.');
        }

        $disk = Storage::disk($targetName);
        if (! $disk instanceof FilesystemAdapter) {
            throw new BadRequestHttpException('Target does not support file downloads.');
        }

        $folder = (string) config('backup.backup.name', config('app.name', 'laravel-backup'));
        $rootReal = realpath($disk->path($folder));
        $pathReal = realpath($disk->path($folder.'/'.$backupName));

        if ($rootReal === false || $pathReal === false || ! is_file($pathReal)) {
            throw new NotFoundHttpException('Backup not found.');
        }

        // Defense in depth against symlink / path-traversal escapes.
        if (! str_starts_with($pathReal.DIRECTORY_SEPARATOR, rtrim($rootReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new NotFoundHttpException('Backup not found.');
        }

        $maxBytes = config('backup-viewer.download.max_bytes');
        if ($maxBytes !== null && filesize($pathReal) > (int) $maxBytes) {
            throw new BadRequestHttpException('Backup exceeds the configured download size limit.');
        }

        return response()->download($pathReal, $backupName, [
            'Content-Type' => 'application/zip',
        ]);
    }
}
