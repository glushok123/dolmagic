<?php

namespace App\Eloquent\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesPrepaymentHistory extends Model
{
    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'id', 'sale_id');
    }

}
