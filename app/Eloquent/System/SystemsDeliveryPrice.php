<?php

namespace App\Eloquent\System;

use Illuminate\Database\Eloquent\Model;

class SystemsDeliveryPrice extends Model
{
    protected $casts = [
        'price' => 'float',
    ];
}
