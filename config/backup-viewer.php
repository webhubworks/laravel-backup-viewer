<?php

return [
    'enabled' => true,

    'route' => [
        'path' => 'backups',
        'name' => 'backup-viewer.index',
        'domain' => null,
    ],

    'middleware' => ['web'],

    'actions' => [
        'run_db_backup' => [
            /*
             * Show a card on the index page that runs `backup:run --only-db`
             * synchronously and streams the resulting .zip back as a
             * download. Set to false to hide the card and refuse the
             * underlying POST endpoint.
             */
            'enabled' => true,
        ],
    ],

    'download' => [
        /*
         * Maximum size (in bytes) for a backup file to be downloadable through
         * the browser. Files larger than this show a "Too large" hint instead
         * of a download button. Set to null to disable the limit entirely.
         */
        'max_bytes' => 500 * 1024 * 1024,
    ],

    /*
     * Free-space ratio below which the disk-usage bar turns red.
     * Example: 0.15 means "warn when less than 15% of the disk is free".
     */
    'low_disk_space_threshold' => 0.15,

    /*
     * How long monitor data is considered fresh. After this many minutes
     * without a backup:monitor run, destinations are flagged stale.
     */
    'monitor_stale_after_minutes' => 1440,

    /*
     * Favicon markup injected into the page's <head>.
     *
     *   - 'html': raw HTML pasted verbatim into <head>. Use this when your
     *             favicon setup includes multiple sizes, media queries,
     *             apple-touch-icon, manifest, theme-color, etc. Just copy
     *             the <link> block out of your main layout.
     *   - 'path': public-relative path to a single favicon (e.g.
     *             '/img/icon.svg'). One <link rel="icon"> is emitted.
     *   - both null: auto-detect /favicon.svg, /favicon.png, /favicon.ico
     *             from public_path().
     */
    'favicon' => [
        'html' => null,
        'path' => null,
    ],
];
