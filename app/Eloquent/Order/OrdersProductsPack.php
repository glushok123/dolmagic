<?php

namespace App\Eloquent\Order;

use App\Observers\Order\OrdersProductsPackObserver;
use Illuminate\Database\Eloquent\Model;

class OrdersProductsPack extends Model
{
    protected $guarded = array();

    protected $casts = [
        'delivery_price' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();
        static::observe(new OrdersProductsPackObserver());
    }

    public function products()
    {
        return $this->hasMany('App\Eloquent\Order\OrdersProduct', 'pack_id', 'id');
    }

    public function deliveryType()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersDeliveriesType', 'id', 'delivery_type_id');
    }

    public function order()
    {
        return $this->hasOne('App\Eloquent\Order\Order', 'id', 'order_id');
    }

    public function getCargoNumberAttribute()
    {
        $cargoNumber = '';
        if(isset($this->order) and isset($this->order->order_system_number))
        {
            $cargoNumber .= $this->order->order_system_number . '-';
        }

        $cargoNumber .= $this->number;

        return $cargoNumber;
    }
}
