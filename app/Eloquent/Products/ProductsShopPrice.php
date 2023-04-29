<?php

namespace App\Eloquent\Products;

use Illuminate\Database\Eloquent\Model;

class ProductsShopPrice extends Model
{
    protected $guarded = array();

    public function user()
    {
        return $this->hasOne('App\User', 'id', 'user_id');
    }

}
