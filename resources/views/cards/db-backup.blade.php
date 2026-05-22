@php
    $enabled = (bool) config('backup-viewer.actions.run_db_backup.enabled', true);

    $hasLocalTarget = collect($byTarget ?? [])
        ->contains(fn ($t) => ($t['isLocal'] ?? false) === true);

    $shouldShow = $enabled && $hasLocalTarget;
@endphp

@if ($shouldShow)
    <div
        class="ls-card ls-action-card"
        x-data="lsDbBackup(@js(route($runDbRouteName)), @js(route($downloadRouteName)))"
        :data-state="state"
    >
        <div class="ls-card__header">
            <h2 class="ls-card__title">{{ __('backup-viewer::messages.db_backup.title') }}</h2>
        </div>

        <div class="ls-card__body ls-action-card__body">
            <button
                type="button"
                class="ls-btn ls-btn--primary ls-action-card__button"
                :disabled="state === 'running'"
                @click="run()"
            >
                <span class="ls-action-card__spinner" x-show="state === 'running' || state === 'done'" x-cloak aria-hidden="true"></span>
                <svg
                    viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"
                    x-show="state === 'idle' || state === 'error'"
                >
                    <path fill="currentColor" d="M8 1a.75.75 0 0 1 .75.75v7.69l2.22-2.22a.75.75 0 1 1 1.06 1.06l-3.5 3.5a.75.75 0 0 1-1.06 0l-3.5-3.5a.75.75 0 1 1 1.06-1.06l2.22 2.22V1.75A.75.75 0 0 1 8 1ZM3 13a.75.75 0 0 1 .75-.75h8.5a.75.75 0 0 1 0 1.5h-8.5A.75.75 0 0 1 3 13Z"/>
                </svg>
                <span x-show="state === 'idle'">{{ __('backup-viewer::messages.db_backup.cta') }}</span>
                <span x-show="state === 'running'" x-cloak>{{ __('backup-viewer::messages.db_backup.running') }}</span>
                <span x-show="state === 'done'" x-cloak>{{ __('backup-viewer::messages.db_backup.done') }}</span>
                <span x-show="state === 'error'" x-cloak>{{ __('backup-viewer::messages.db_backup.retry') }}</span>
            </button>

            <pre
                class="ls-action-card__output"
                x-show="output !== ''"
                x-ref="output"
                x-text="output"
                x-cloak
            ></pre>

            <p class="ls-action-card__error" x-show="state === 'error'" x-text="errorMessage" x-cloak></p>
        </div>
    </div>
@endif
