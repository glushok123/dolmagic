<?php

namespace App\Eloquent\Shops;

use Illuminate\Database\Eloquent\Model;

class ShopProductsPostfix extends Model
{
    public function shop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'shop_id');
    }
}
