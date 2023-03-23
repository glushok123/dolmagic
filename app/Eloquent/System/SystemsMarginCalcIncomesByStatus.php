<?php

namespace App\Eloquent\System;

use Illuminate\Database\Eloquent\Model;

class SystemsMarginCalcIncomesByStatus extends Model
{
    protected $fillable = ['type_shop_id', 'sales_products_status_id', 'income_id'];
}
