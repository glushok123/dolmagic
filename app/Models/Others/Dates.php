<?php

namespace App\Models\Others;

use App\Models\Model;
use Carbon\Carbon;

class Dates extends Model
{
    public static function checkWeekend(Carbon $CarbonDate): bool
    {
        $weekends = [
            '2023-01-01',
            '2023-01-02',
            '2023-01-03',
            '2023-01-07',
        ];

        if($CarbonDate->isWeekend()) return true;

        foreach($weekends as $weekendDate)
        {
            if($CarbonDate->startOfDay() == Carbon::parse($CarbonDate->getTimezone())->startOfDay())
            {
                return true;
            }
        }

        return false;
    }

    public static function countDaysExceptWeekends($days, $fromDate = false): \stdClass
    {
        $res = new \stdClass();
        $res->countedDays = 0;
        $res->countedDate = false;

        $Date = $fromDate?Carbon::parse($fromDate):Carbon::now();
        $Date->setTimezone('Europe/Moscow');

        for($i=0; $i<$days; $i++)
        {
            $Date = $Date->addDay(1);
            $stop = 0;
            while(Dates::checkWeekend($Date) or ($stop > 100))
            {
                $Date = $Date->addDay(1);
                $stop++;
                $res->countedDays++;
            }
            $res->countedDays++;
        }

        $res->countedDate = $Date;

        return $res;
    }
}


