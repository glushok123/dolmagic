<?php

namespace App\Eloquent\Products;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ProductsAvailableDifference extends Model
{

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'id', 'product_id');
    }


    public function getCreatedTimeAttribute()
    {
        return Carbon::parse($this->created_at)->setTimezone('Europe/Moscow')->format('Y-m-d H:i:s');
    }

    public function availableCrms()
    {
        return $this->hasMany('App\Eloquent\Products\ProductsAvailableCrm', 'products_available_difference_id', 'id');
    }


}

