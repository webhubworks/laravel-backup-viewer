<?php

namespace Webhub\BackupViewer;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use RuntimeException;

/**
 * Public entry point for host apps to configure the backup view.
 *
 * Usage in AppServiceProvider::boot():
 *
 *   BackupViewer::auth(function (Request $request) {
 *       return $request->user()?->is_admin === true;
 *   });
 */
class BackupViewer
{
    /** @var (Closure(Request): bool)|null */
    public static ?Closure $authCallback = null;

    /**
     * Set the callback used to authorize access to the backup view.
     *
     * @param  Closure(Request): bool  $callback
     */
    public static function auth(Closure $callback): void
    {
        static::$authCallback = $callback;
    }

    /**
     * Determine whether the given request is authorized to view the backup page.
     */
    public static function check(Request $request): bool
    {
        if (static::$authCallback !== null) {
            return (bool) (static::$authCallback)($request);
        }

        return app()->environment('local');
    }

    /**
     * Inline <style> block for the backup view, read straight from the
     * package's compiled dist. No publish step required in the host app —
     * same pattern as Laravel Horizon's Horizon::css().
     */
    public static function css(): HtmlString
    {
        $css = @file_get_contents(__DIR__.'/../resources/dist/backup.css');

        if ($css === false) {
            throw new RuntimeException('Unable to load the laravel-backup-viewer CSS.');
        }

        return new HtmlString("<style>{$css}</style>");
    }

    /**
     * Inline <script> block for the backup view.
     */
    public static function js(): HtmlString
    {
        $js = @file_get_contents(__DIR__.'/../resources/dist/backup.js');

        if ($js === false) {
            throw new RuntimeException('Unable to load the laravel-backup-viewer JavaScript.');
        }

        return new HtmlString("<script>{$js}</script>");
    }
}
