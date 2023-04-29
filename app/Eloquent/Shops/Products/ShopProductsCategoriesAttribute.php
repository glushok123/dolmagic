<?php

namespace App\Eloquent\Shops\Products;

use Illuminate\Database\Eloquent\Model;

class ShopProductsCategoriesAttribute extends Model
{

    protected $guarded = [];

    public function values()
    {
        return $this->hasMany(
            'App\Eloquent\Shops\Products\ShopProductsCategoriesAttributesValue',
            'shop_products_categories_attribute_id', 'id')->where('shop_id', $this->shop_id);
    }

}
