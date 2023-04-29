<?php

namespace App\Eloquent\Products;

use App\Eloquent\Shops\Products\ShopProductsCategoriesAttributesValue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ProductShopCategoriesAttribute extends Model
{

    protected $guarded = array();

    public function attributeValue()
    {
        return $this->hasOne('App\Eloquent\Shops\Products\ShopProductsCategoriesAttributesValue', 'id', 'shop_products_categories_attributes_value_id');
    }


    public function getUpdatedDateTimeAttribute(): string
    {
        return Carbon::parse($this->updated_at)->setTimezone('Europe/Moscow')->toDateTimeString();
    }

    public function getStringValueAttribute()
    {
        if($this->value) return $this->value;

        if($this->shop_products_categories_attributes_value_id)
        {
            if($ShopProductsCategoriesAttributesValue = ShopProductsCategoriesAttributesValue::where([
                'shop_id' => $this->shop_id,
                'id' => $this->shop_products_categories_attributes_value_id,
            ])->first())
            {
                return $ShopProductsCategoriesAttributesValue->value;
            }
        }

        return '';

    }
}
