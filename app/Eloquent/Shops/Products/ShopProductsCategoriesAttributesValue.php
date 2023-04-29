<?php

namespace App\Eloquent\Shops\Products;

use Illuminate\Database\Eloquent\Model;

class ShopProductsCategoriesAttributesValue extends Model
{

    protected $guarded = [];

    public function shopAttribute()
    {
        return $this->hasOne(
            'App\Eloquent\Shops\Products\ShopProductsCategoriesAttribute',
            'id',
            'shop_products_categories_attribute_id'
        );
    }

}
