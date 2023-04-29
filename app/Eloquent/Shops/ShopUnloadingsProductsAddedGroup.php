<?php

namespace App\Eloquent\Shops;

use Illuminate\Database\Eloquent\Model;

class ShopUnloadingsProductsAddedGroup extends Model
{

    public function getStrPadIdAttribute()
    {
        return str_pad($this->id,10,'6',STR_PAD_LEFT);
    }
}
