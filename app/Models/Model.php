<?php

namespace App\Models;

use App\Eloquent\Other\ModelsLog;

class Model
{
    public function __construct()
    {

    }

    public static function log($type, $fName, $comment = false, $code_info = false)
    {
        $ModelsLog = new ModelsLog;
        $ModelsLog->type = $type;
        $ModelsLog->f_name = $fName;

        if($comment)    $ModelsLog->comment = is_string($comment)?$comment:json_encode($comment);
        if($code_info)  $ModelsLog->code_info = is_string($code_info)?$code_info:json_encode($code_info);

        $ModelsLog->save();
    }
}
