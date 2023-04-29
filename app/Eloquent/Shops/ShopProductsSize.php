<?php

namespace App\Eloquent\Shops;

use Illuminate\Database\Eloquent\Model;

class ShopProductsSize extends Model
{
    protected $fillable = ['product_id', 'shop_id'];
}
