<?php

namespace App\Eloquent\Shops\Products;

use Illuminate\Database\Eloquent\Model;

class ShopProductsGroup extends Model
{



    public function parent()
    {
        return $this->hasOne('App\Eloquent\Shops\Products\ShopProductsGroup', 'shop_group_id', 'shop_parent_group_id');
    }

    public function childs()
    {
        return $this->hasMany('App\Eloquent\Shops\Products\ShopProductsGroup', 'shop_parent_group_id', 'shop_group_id');
    }

}
