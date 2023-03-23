<?php

namespace App\Eloquent\System;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SystemsCommission extends Model
{

    protected $casts = [
        'value_percent' => 'float',
        'value_min' => 'float',
        'deduction_percent' => 'float',
        'deduction_min' => 'float',
    ];
}
