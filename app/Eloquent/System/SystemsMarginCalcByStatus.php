<?php

namespace App\Eloquent\System;

use Illuminate\Database\Eloquent\Model;

class SystemsMarginCalcByStatus extends Model
{
    protected $fillable = ['type_shop_id', 'sales_products_status_id'];
}
