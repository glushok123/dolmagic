<?php

namespace App\Eloquent\Shops;

use Illuminate\Database\Eloquent\Model;

class ShopUnloadingsProductsTitle extends Model
{

    public function unloading()
    {
        return $this->hasOne('App\Eloquent\Shops\ShopUnloading', 'id', 'shop_unloading_id');
    }

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'id', 'product_id');
    }
}
