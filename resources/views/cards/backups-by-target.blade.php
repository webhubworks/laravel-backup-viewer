@php
    use Webhub\BackupViewer\Support\Format;

    $targetNames = array_keys($byTarget);
    $initialTab = $targetNames[0] ?? '';
@endphp

<div class="ls-card ls-card--full" x-data="lsTabs(@js($initialTab))">
    <div class="ls-card__header">
        <h2 class="ls-card__title">{{ __('backup-viewer::messages.backups_table.title') }}</h2>
    </div>

    <div class="ls-card__body">
        @if (empty($byTarget))
            <div class="ls-card__empty">{{ __('backup-viewer::messages.backups_table.no_targets') }}</div>
        @else
            @if (count($targetNames) > 1)
                <div class="ls-tabs" role="tablist">
                    @foreach ($targetNames as $name)
                        <button
                            type="button"
                            role="tab"
                            class="ls-tabs__tab"
                            :aria-selected="isActive(@js($name)) ? 'true' : 'false'"
                            @click="select(@js($name))"
                        >{{ $name }}</button>
                    @endforeach
                </div>
            @endif

            @foreach ($byTarget as $name => $target)
                <div
                    class="ls-tab-pane"
                    role="tabpanel"
                    x-show="isActive(@js($name))"
                    x-cloak
                >
                    @php
                        $showDownload = $target['isLocal'];
                    @endphp

                    @if (! empty($target['location']))
                        <div class="ls-target-location" title="{{ __('backup-viewer::messages.backups_table.storage_location') }}">
                            <span class="ls-target-location__driver">{{ $target['driver'] ?? 'disk' }}</span>
                            <span class="ls-target-location__path">{{ $target['location'] }}</span>
                        </div>
                    @endif

                    @if (! empty($target['backups']))
                        <div class="ls-table-wrap">
                            <table class="ls-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('backup-viewer::messages.backups_table.col_file') }}</th>
                                        <th>{{ __('backup-viewer::messages.backups_table.col_size') }}</th>
                                        <th>{{ __('backup-viewer::messages.backups_table.col_created') }}</th>
                                        @if ($showDownload)
                                            <th class="text-right"></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($target['backups'] as $backup)
                                        @php
                                            $tooLarge = $showDownload
                                                && $downloadMaxBytes !== null
                                                && $backup['size'] > $downloadMaxBytes;
                                        @endphp
                                        <tr>
                                            <td class="ls-mono">
                                                {{ $backup['name'] }}
                                                @if ($backup['encrypted'] === true)
                                                    <span class="ls-lock" title="{{ __('backup-viewer::messages.backups_table.password_protected') }}" aria-label="{{ __('backup-viewer::messages.backups_table.password_protected') }}">
                                                        <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">
                                                            <path fill="currentColor" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V7H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1h-.5V4.5A3.5 3.5 0 0 0 8 1Zm-2 6V4.5a2 2 0 1 1 4 0V7H6Z"/>
                                                        </svg>
                                                    </span>
                                                @elseif ($backup['encrypted'] === false)
                                                    <span class="ls-lock ls-lock--open" title="{{ __('backup-viewer::messages.backups_table.not_password_protected') }}" aria-label="{{ __('backup-viewer::messages.backups_table.not_password_protected') }}">
                                                        <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">
                                                            <path fill="currentColor" d="M11.5 7V4.5a3.5 3.5 0 0 0-6.93-.66 .75.75 0 0 0 1.47.28 2 2 0 0 1 3.96.38V7H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1h-.5Z"/>
                                                        </svg>
                                                    </span>
                                                @endif
                                            </td>
                                            <td>{{ Format::bytes($backup['size']) }}</td>
                                            <td>{{ Format::relativeTime($backup['modified']) }}</td>
                                            @if ($showDownload)
                                                <td class="text-right">
                                                    @if ($tooLarge)
                                                        <span
                                                            class="ls-btn ls-btn--disabled"
                                                            aria-disabled="true"
                                                            title="{{ __('backup-viewer::messages.backups_table.too_large_title', ['size' => Format::bytes($backup['size']), 'limit' => Format::bytes((int) $downloadMaxBytes)]) }}"
                                                        >
                                                            {{ __('backup-viewer::messages.backups_table.too_large') }}
                                                        </span>
                                                    @else
                                                        <form method="post" action="{{ route($downloadRouteName) }}">
                                                            @csrf
                                                            <input type="hidden" name="targetName" value="{{ $name }}">
                                                            <input type="hidden" name="backupName" value="{{ $backup['name'] }}">
                                                            <button type="submit" class="ls-btn">
                                                                <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">
                                                                    <path fill="currentColor" d="M8 1a.75.75 0 0 1 .75.75v7.69l2.22-2.22a.75.75 0 1 1 1.06 1.06l-3.5 3.5a.75.75 0 0 1-1.06 0l-3.5-3.5a.75.75 0 1 1 1.06-1.06l2.22 2.22V1.75A.75.75 0 0 1 8 1ZM3 13a.75.75 0 0 1 .75-.75h8.5a.75.75 0 0 1 0 1.5h-8.5A.75.75 0 0 1 3 13Z"/>
                                                                </svg>
                                                                {{ __('backup-viewer::messages.backups_table.download') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="ls-card__empty">{{ __('backup-viewer::messages.backups_table.no_backups') }}</div>
                    @endif

                    <div class="ls-legend">
                        @if ($target['isLocal'])
                            <p>
                                <span class="ls-lock" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">
                                        <path fill="currentColor" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V7H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1h-.5V4.5A3.5 3.5 0 0 0 8 1Zm-2 6V4.5a2 2 0 1 1 4 0V7H6Z"/>
                                    </svg>
                                </span>
                                {{ __('backup-viewer::messages.backups_table.file_password_protected') }}
                            </p>
                            <p>
                                <span class="ls-lock ls-lock--open" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">
                                        <path fill="currentColor" d="M11.5 7V4.5a3.5 3.5 0 0 0-6.93-.66 .75.75 0 0 0 1.47.28 2 2 0 0 1 3.96.38V7H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1h-.5Z"/>
                                    </svg>
                                </span>
                                {{ __('backup-viewer::messages.backups_table.file_not_password_protected') }}
                            </p>
                        @endif
                        <p class="ls-legend__note">{{ __('backup-viewer::messages.backups_table.zip_detection_note') }}</p>
                    </div>

                    @if ($target['diskUsage'] !== null)
                        @php
                            $total = (int) $target['diskUsage']['total'];
                            $free = (int) $target['diskUsage']['free'];
                            $used = max(0, $total - $free);
                            $backupsBytes = (int) ($target['backupsBytes'] ?? 0);
                            $backupsBytesShown = min($backupsBytes, $used);
                            $usedPct = $total > 0 ? ($used / $total) * 100 : 0;
                            $backupsPct = $total > 0 ? ($backupsBytesShown / $total) * 100 : 0;
                            $threshold = $lowDiskSpaceThreshold > 0 ? (int) round($total * $lowDiskSpaceThreshold) : null;
                            $isLow = $threshold !== null && $free < $threshold;
                            $thresholdPct = ($threshold !== null && $total > 0) ? (($total - $threshold) / $total) * 100 : null;
                        @endphp

                        <div class="ls-disk @if ($isLow) ls-disk--low @endif">
                            <div
                                class="ls-disk__bar"
                                role="img"
                                aria-label="{{ __('backup-viewer::messages.disk.usage_aria', ['used' => Format::bytes($used), 'total' => Format::bytes($total), 'free' => Format::bytes($free)]) }}"
                            >
                                <div class="ls-disk__used" style="width: {{ number_format($usedPct, 2, '.', '') }}%;"></div>
                                @if ($backupsPct > 0)
                                    <div
                                        class="ls-disk__backups"
                                        style="width: {{ number_format($backupsPct, 2, '.', '') }}%;"
                                        title="{{ __('backup-viewer::messages.disk.backups_title', ['size' => Format::bytes($backupsBytes)]) }}"
                                    ></div>
                                @endif
                                @if ($thresholdPct !== null)
                                    <div
                                        class="ls-disk__threshold"
                                        style="left: {{ number_format($thresholdPct, 2, '.', '') }}%;"
                                        title="{{ __('backup-viewer::messages.disk.threshold_title', ['free' => Format::bytes($threshold)]) }}"
                                    ></div>
                                @endif
                            </div>
                            <div class="ls-disk__legend">
                                @if ($backupsBytes > 0)
                                    <span class="ls-disk__legend-item">
                                        <span class="ls-disk__swatch ls-disk__swatch--backups" aria-hidden="true"></span>
                                        <strong>{{ Format::bytes($backupsBytes) }}</strong>&nbsp;{{ __('backup-viewer::messages.disk.backups_label') }}
                                    </span>
                                @endif
                                <span class="ls-disk__legend-item">
                                    <strong>{{ Format::bytes($used) }}</strong>&nbsp;{{ __('backup-viewer::messages.disk.used') }}
                                </span>
                                <span class="ls-disk__legend-item">
                                    <strong>{{ Format::bytes($free) }}</strong>&nbsp;{{ __('backup-viewer::messages.disk.free') }}
                                </span>
                                <span class="ls-disk__legend-item ls-disk__legend-item--total">
                                    {{ __('backup-viewer::messages.disk.of') }} {{ Format::bytes($total) }}
                                </span>
                                @if ($threshold !== null)
                                    <span class="ls-disk__legend-item">
                                        {{ __('backup-viewer::messages.disk.warn_below', ['free' => Format::bytes($threshold), 'pct' => number_format($lowDiskSpaceThreshold * 100, 0)]) }}
                                    </span>
                                @endif
                            </div>
                            @if ($isLow)
                                <p class="ls-disk__warning">
                                    {{ __('backup-viewer::messages.disk.low_warning') }}
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</div>
