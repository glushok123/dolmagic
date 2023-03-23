<?php

namespace App\Eloquent\System;

use Illuminate\Database\Eloquent\Model;

class SystemsOrdersStatus extends Model
{
    public function ordersStatus()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersStatus', 'id', 'orders_status_id');
    }
}
