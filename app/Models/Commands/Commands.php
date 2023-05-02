<?php

namespace App\Models\Commands;

use App\Eloquent\Commands\CommandsStat;
use App\Models\Model;
use Carbon\Carbon;


class Commands extends Model
{



    public static function commandLog($signature, $task)
    {
        if($startKey = self::commandLogStart($signature))
        {
            try
            {
                $task();
            }catch(\Exception $e){
                self::commandLogCatch($signature, $startKey);
            }
            self::commandLogStop($signature, $startKey);
        }
    }


    public static function commandLogStart($signature)
    {
        // There is can make unique start
        $diffMinutesSet = 10;
        switch($signature)
        {
            case 'ozon2:msk:actions:products:auto:options':
                    $diffMinutesSet = 60*6;
                break;
            case 'ozon:actions:products:auto:options':
                    $diffMinutesSet = 120;
                break;
            case 'unloadings:save-all-yml':
                    $diffMinutesSet = 30;
                break;
        }

        if($CommandStatStarted = CommandsStat::where([
            ['signature', $signature],
            ['end', 0],
        ])->orderBy('id', 'DESC')->first())
        {
            $queuePrevent = false;
            $CommandStatStarted->queue_call = $CommandStatStarted->queue_call + 1;

            $diffMinutes = Carbon::parse($CommandStatStarted->created_at)->diffInMinutes(Carbon::now());
            if($diffMinutes < $diffMinutesSet)
            {
                $CommandStatStarted->queue_prevent = $CommandStatStarted->queue_prevent + 1;
                $queuePrevent = true;
            }else
            {
                $CommandStatStarted->queue_start = $CommandStatStarted->queue_start + 1;
                $CommandStatStarted->queue_aborted = 1;
                $CommandStatStarted->end = 1;
            }

            $CommandStatStarted->save();

            if($queuePrevent) return false; // ONLY IF MORE THAN XXX MIN
        }

        $startKey = uniqid();
        $CommandStat = new CommandsStat;
        $CommandStat->signature = $signature;
        $CommandStat->start_key = $startKey;
        $CommandStat->server = config('app.name');
        if($CommandStat->save()) return $startKey;

        return false;
    }

    public static function commandLogStop($signature, $startKey): bool
    {
        if($CommandStat = CommandsStat::where([
            ['signature', $signature],
            ['start_key', $startKey],
        ])->first())
        {
            $CommandStat->end = 1;
            $CommandStat->memory_peak_usage_mb = memory_get_peak_usage()/1024/1024;
            return $CommandStat->save();
        }

        return false;
    }

    public static function commandLogCatch($signature, $startKey): bool
    {
        if($CommandStat = CommandsStat::where([
            ['signature', $signature],
            ['start_key', $startKey],
        ])->first())
        {
            $CommandStat->catch = 1;
            return $CommandStat->save();
        }

        return false;
    }


}


