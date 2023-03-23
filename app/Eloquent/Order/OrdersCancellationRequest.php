<?php

namespace App\Eloquent\Order;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class OrdersCancellationRequest extends Model
{
    protected $guarded = array();

    public function getAcceptedDateTimeAttribute()
    {
        return Carbon::parse($this->accepted_date)->setTimezone('Europe/Moscow')->toDateTimeString();
    }

    public function order()
    {
        return $this->hasOne('App\Eloquent\Order\Order', 'id', 'order_id');
    }

    public function user()
    {
        return $this->hasOne('App\User', 'id', 'user_id');
    }
}
