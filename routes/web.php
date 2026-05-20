<?php

use Illuminate\Support\Facades\Route;
use Webhub\BackupViewer\Http\Controllers\BackupController;
use Webhub\BackupViewer\Http\Controllers\DownloadBackupController;

Route::get('/', [BackupController::class, 'show'])->name('');
Route::post('/download', DownloadBackupController::class)->name('.download');
