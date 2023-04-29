<?php

namespace App\Eloquent\Shops;

use Illuminate\Database\Eloquent\Model;

class ShopUnloadingsProductsOption extends Model
{

    public function unloading()
    {
        return $this->hasOne('App\Eloquent\Shops\ShopUnloading', 'id', 'shop_unloading_id');
    }

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'id', 'product_id');
    }

    public function addedGroup()
    {
        return $this->hasOne('App\Eloquent\Shops\ShopUnloadingsProductsAddedGroup', 'id', 'new_group_id');
    }

    public function getStrPadNewGroupIdAttribute()
    {
        return str_pad($this->new_group_id,10,'6',STR_PAD_LEFT);
    }
}
