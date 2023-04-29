<?php

namespace App\Eloquent\Products;

use Illuminate\Database\Eloquent\Model;

class TypeShopProductsOption extends Model
{
    protected $fillable = ['product_id', 'shop_id'];
}
