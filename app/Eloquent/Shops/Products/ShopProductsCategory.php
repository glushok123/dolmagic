<?php

namespace App\Eloquent\Shops\Products;

use App\Models\Shops\ShopProducts;
use Illuminate\Database\Eloquent\Model;

class ShopProductsCategory extends Model
{

    protected $guarded = [];

    protected $appends = ['fullName'];

    /*
    public function attributes()
    {
        return $this->hasManyThrough(
            'App\Eloquent\Shops\Products\ShopProductsCategoriesAttribute',
            'App\Eloquent\Shops\Products\ShopProductsCategoriesToAttribute',
            'shop_products_category_id',
            'id', // ok
            'id', // ok
            'shop_products_categories_attribute_id' // ok
        )->orderBy('is_required', 'DESC');
    }

*/

    public function getAttributesAttribute()
    {
        $ShopProductsCategory = ShopProductsCategory::where([
            ['shop_category_id', $this->shop_category_id],
            ['shop_id', $this->shop_id],
        ])->first();
        $ids = ShopProductsCategoriesToAttribute::where('shop_products_category_id', $ShopProductsCategory->id)->pluck('shop_products_categories_attribute_id')->toArray();
        return ShopProductsCategoriesAttribute::whereIn('id', $ids)->get();
    }

    public function parentCategory()
    {
        return $this->hasOne('App\Eloquent\Shops\Products\ShopProductsCategory', 'shop_category_id', 'shop_parent_category_id');
    }

    public function getFullNameAttribute()
    {
        return ShopProducts::shopProductsCategoriesGetFullName($this);
    }

}
