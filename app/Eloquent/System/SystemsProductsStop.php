<?php

namespace App\Eloquent\System;

use App\Models\Others\Ozon;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SystemsProductsStop extends Model
{
    protected $guarded = array();

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'id', 'product_id');
    }

    public function user()
    {
        return $this->hasOne('App\User', 'id', 'user_id');
    }

    public function typeShop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'orders_type_shop_id');
    }



    public function getCreatedTimeAttribute()
    {
        return Carbon::parse($this->created_at)->setTimezone('Europe/Moscow')->format('Y-m-d H:i:s');
    }
    public function getUpdatedTimeAttribute()
    {
        return Carbon::parse($this->updated_at)->setTimezone('Europe/Moscow')->format('Y-m-d H:i:s');
    }

    public function getStopStockUntilDateAttribute()
    {
        return Carbon::parse($this->stop_stock_until)->format('Y-m-d');
    }

    public function getStopStockUntilTimeAttribute()
    {
        return Carbon::parse($this->stop_stock_until)->format('H:i');
    }

    public function getStopPriceUntilDateAttribute()
    {
        return Carbon::parse($this->stop_price_until)->format('Y-m-d');
    }

    public function getStopPriceUntilTimeAttribute()
    {
        return Carbon::parse($this->stop_price_until)->format('H:i');
    }

    public function getStopImageUntilDateAttribute()
    {
        return Carbon::parse($this->stop_image_until)->format('Y-m-d');
    }

    public function getStopImageUntilTimeAttribute()
    {
        return Carbon::parse($this->stop_image_until)->format('H:i');
    }

    public function getEvaluativeAttribute()
    {
        return Ozon::getProductEvaluative($this);
    }

}
