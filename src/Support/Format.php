<?php

namespace Webhub\BackupViewer\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\HtmlString;

class Format
{
    public static function bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB', 'PB'];
        $value = (float) $bytes / 1024;

        foreach ($units as $i => $unit) {
            if ($value < 1024 || $i === count($units) - 1) {
                return number_format($value, $value >= 100 ? 0 : ($value >= 10 ? 1 : 2)).' '.$unit;
            }
            $value /= 1024;
        }

        return $bytes.' B';
    }

    /**
     * Render a relative-time label wrapped in a <time> element whose
     * tooltip surfaces the exact UTC timestamp. Returning HtmlString lets
     * existing call sites stay on Blade's {{ }} syntax without double
     * escaping the markup.
     */
    public static function relativeTime(int $timestamp): HtmlString
    {
        /** @var CarbonInterface $date */
        $date = Date::createFromTimestamp($timestamp);

        $human = $date->diffForHumans();
        $utc = $date->copy()->utc()->format('Y-m-d H:i:s').' UTC';

        return new HtmlString(sprintf(
            '<time class="ls-time" datetime="%s" data-tooltip="%s">%s</time>',
            e($date->toIso8601String()),
            e($utc),
            e($human)
        ));
    }
}
