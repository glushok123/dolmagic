<?php

namespace App\Eloquent\Products;

use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    protected $guarded = array();

    public function getClassAttribute()
    {
        $statusClass = '';
        switch($this->attributes['state'])
        {
            case -1:
                $statusClass = ' table-danger';
                break;
            case 0:
                $statusClass = ' table-warning';
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
        return route('products.categories.edit', ['id' => $this->id]);
    }

    public function ozonCategory()
    {
        return $this->hasOne('App\Eloquent\Shops\Products\ShopProductsCategory', 'shop_category_id', 'ozon_id')
            ->where('shop_id', 1);
    }

    public function products()
    {
        return $this->hasMany('App\Eloquent\Products\Product', 'category_id', 'id');
    }

    public function getProdctsCountAttribute()
    {
        return count($this->products);
    }
}
