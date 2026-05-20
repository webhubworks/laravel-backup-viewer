# Changelog

All notable changes to `laravel-backup-viewer` will be documented in this file.

## 1.0.0 — Initial release

- Backup health card (last run, last successful run, monitor results, scheduled commands)
- Per-target checks card (one section per disk × backup-name; reachability, configured spatie checks, synthetic free-disk check on local disks)
- Backups by target card (file table with download, lock-state, disk usage bar; one tab per disk)
- Notifications card (event → channel → recipient routing)
- Event-driven persistence: BackupHasFailed / BackupWasSuccessful / HealthyBackupWasFound / UnhealthyBackupWasFound are recorded to a small JSON state file on the first local backup disk
- Gate-based auth following Laravel Horizon's `BackupViewer::auth(...)` pattern (default: local env only)
- Pre-compiled Tailwind + Alpine assets inlined into the response — no `vendor:publish` required for the frontend
