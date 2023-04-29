<?php

namespace App\Eloquent\Products;

use Illuminate\Database\Eloquent\Model;

class TypeShopProduct extends Model
{
    protected $fillable = ['type_shop_id', 'product_id'];

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'id', 'product_id');
    }

    public function shop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'type_shop_id');
    }
}
