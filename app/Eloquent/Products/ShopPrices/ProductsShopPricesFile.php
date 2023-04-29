<?php

namespace App\Eloquent\Products\ShopPrices;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ProductsShopPricesFile extends Model
{
    public function preloadedPrices()
    {
        return $this->hasMany('App\Eloquent\Products\ShopPrices\ProductsShopPricesPreload', 'file_id', 'id');
    }

    public function shop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'shop_id');
    }

    public function getFileSrcAttribute()
    {
        return asset('/storage/files/products/shop-prices/'.$this->filename);
    }

    public function getLocalPathAttribute()
    {
        return 'files/products/shop-prices/'.$this->filename;
    }

    public function getDateTimeAttribute()
    {
        return Carbon::parse($this->created_at)->setTimezone('Europe/Moscow')->toDateTimeString();
    }

    public function getATagAttribute()
    {
        return "<a href = '$this->FileSrc'>$this->original_name</a>";
    }
}
