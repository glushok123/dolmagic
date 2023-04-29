<?php

namespace App\Eloquent\Shops;

use Illuminate\Database\Eloquent\Model;

class ShopUnloadingsUpPrice extends Model
{
    protected $casts = [
        'price_min' => 'float',
        'price_max' => 'float',
        'up_price' => 'float',
    ];

    public function unloading()
    {
        return $this->hasOne('App\Eloquent\Shops\ShopUnloading', 'id', 'shop_unloading_id');
    }

    public function getPriceTypeSignAttribute()
    {
        $sign = '';
        switch($this->up_price_type_id)
        {
            case 1: return '₽';
            case 2: return '%';
        }

        return $sign;
    }
}
