<?php

namespace App\Eloquent\Shops;

use Illuminate\Database\Eloquent\Model;

class ShopUpOption extends Model
{
    public function valueTypeMore()
    {
        return $this->hasOne('App\Eloquent\Directories\ValueType', 'id', 'importUpPriceMoreTypeId');
    }

    public function valueTypeLess()
    {
        return $this->hasOne('App\Eloquent\Directories\ValueType', 'id', 'importUpPriceLessTypeId');
    }

    public function user()
    {
        return $this->hasOne('App\User', 'id', 'user_id');
    }
}
