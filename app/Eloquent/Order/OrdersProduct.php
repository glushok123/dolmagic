<?php

namespace App\Eloquent\Order;

use App\Observers\Order\OrdersProductObserver;
use Illuminate\Database\Eloquent\Model;

class OrdersProduct extends Model
{
    protected $guarded = array();

    protected $casts = [
        'product_price' => 'float',
        'product_discount' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();
        static::observe(new OrdersProductObserver());
    }

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'id', 'product_id');
    }

    public function group()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersProductsPack', 'id', 'group_id');
    }

    public function getProductTotalPriceAttribute()
    {
        return $this->product_price * $this->product_quantity;
    }

    public function getProductTotalDiscountAttribute()
    {
        return $this->product_discount * $this->product_quantity;
    }
}
