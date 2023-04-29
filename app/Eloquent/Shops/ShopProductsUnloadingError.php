<?php

namespace App\Eloquent\Shops;

use Illuminate\Database\Eloquent\Model;

class ShopProductsUnloadingError extends Model
{
    public function shop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'shop_id');
    }

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'sku', 'sku');
    }
}
