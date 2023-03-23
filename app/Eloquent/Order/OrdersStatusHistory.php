<?php

namespace App\Eloquent\Order;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class OrdersStatusHistory extends Model
{
    public function order()
    {
        return $this->hasOne('App\Eloquent\Order\Order', 'id', 'order_id');
    }

    public function systemStatusBefore()
    {
        return $this->hasOne('App\Eloquent\System\SystemsOrdersStatus', 'id', 'sytem_old_status_id');
    }

    public function systemStatusAfter()
    {
        return $this->hasOne('App\Eloquent\System\SystemsOrdersStatus', 'id', 'system_status_id');
    }

    //Mutators
    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value, 'UTC')->setTimezone('Europe/Moscow')->toDateTimeString();
    }
}
