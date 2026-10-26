<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class SchoolYear
{
    public static function startYear(?CarbonInterface $date = null): int
    {
        $date ??= Carbon::now();

        $startMonth = (int) config('placement.school_year_start_month', 6);

        return $date->month >= $startMonth ? $date->year : $date->year - 1;
    }
}
