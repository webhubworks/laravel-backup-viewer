<?php

use Illuminate\Support\Facades\Route;
use Webhub\BackupViewer\Http\Controllers\BackupController;
use Webhub\BackupViewer\Http\Controllers\DownloadBackupController;
use Webhub\BackupViewer\Http\Controllers\RunDbBackupController;

Route::get('/', [BackupController::class, 'show'])->name('');
Route::post('/download', DownloadBackupController::class)->name('.download');
Route::post('/run-db', RunDbBackupController::class)->name('.run-db');
