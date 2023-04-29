<?php

namespace App\Eloquent\Products;

use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    protected $guarded = array();

    public function getClassAttribute()
    {
        $statusClass = '';
        switch($this->attributes['state']){
            case -1:
                $statusClass = ' table-danger';
                break;
            case 0:
                $statusClass = ' ';
                //$statusClass = ' table-warning';
                break;
            case 1:

                break;
            case 2:
                $statusClass = ' table-success';
                break;
        };
        return $statusClass;
    }

    public function getEditPathAttribute()
    {
        return route('products.types.edit', ['id' => $this->id]);
    }

    public function ozonType()
    {
        return $this->hasOne('App\Eloquent\Shops\Products\ShopProductsCategoriesAttributesValue',
            'shop_value_id',
            'ozon_id'
        )->where('shop_id', 1);
    }

    public function products()
    {
        return $this->hasMany('App\Eloquent\Products\Product', 'type_id', 'id');
    }

    public function getProdctsCountAttribute()
    {
        return count($this->products);
    }

}
