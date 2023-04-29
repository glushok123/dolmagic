<?php

namespace App\Eloquent\Products;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ProductShopCategory extends Model
{

    protected $guarded = array();

    public function shopCategory()
    {
        return $this->hasOne('App\Eloquent\Shops\Products\ShopProductsCategory', 'id', 'shop_products_category_id');
    }



    public function shopAttributes()
    {
        return $this->hasMany('App\Eloquent\Products\ProductShopCategoriesAttribute',
            'product_id',
            'product_id')->where('shop_id', $this->shop_id);
    }

    public function shopAttribute($attributeId)
    {
        return $this->shopAttributes->where('shop_products_categories_attribute_id', $attributeId)->first();
    }

    public function getUpdatedDateTimeAttribute(): string
    {
        return Carbon::parse($this->updated_at)->setTimezone('Europe/Moscow')->toDateTimeString();
    }

    public function user()
    {
        return $this->hasOne('App\User', 'id', 'user_id');
    }
}
