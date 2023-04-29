<?php

namespace App\Models\Others;

use App\Models\Model;

class Log extends Model
{

    public static function success($mess)
    {
        dump($mess);
    }

    public static function crash($mess)
    {
        dd($mess);
    }

    public static function counting($now, $max, $text = false, $oneRow = true)
    {
        $mess = "Updating ".($now + 1)." of $max. $text";
        if($oneRow)
        {
            if($now > 0) echo "\r";
            print_r($mess);
        }else
        {
            dump($mess);
        }

    }
}


