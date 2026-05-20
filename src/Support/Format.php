<?php

namespace Webhub\BackupViewer\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

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

    public static function relativeTime(int $timestamp): string
    {
        /** @var CarbonInterface $date */
        $date = Date::createFromTimestamp($timestamp);

        return $date->diffForHumans();
    }
}
