<?php

namespace App\Eloquent\Products;

use Illuminate\Database\Eloquent\Model;

class ProductImagesShopRule extends Model
{
    public function positions()
    {
        return $this
            ->hasMany(
                'App\Eloquent\Products\ProductImagesShopRulesPosition',
                'product_images_shop_rule_id',
                'id'
            )->orderBy('position', 'ASC');
    }

    public function shop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'shop_id');
    }

    public function getMaxImagesAttribute()
    {
        return $this->shop->max_images??false;
    }
}
