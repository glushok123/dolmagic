<?php

namespace App\Eloquent\Products\ShopPrices;

use Illuminate\Database\Eloquent\Model;

class ProductsShopPricesPreload extends Model
{
    protected $casts = [
        'price' => 'float',
    ];

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'id', 'product_id');
    }
}
