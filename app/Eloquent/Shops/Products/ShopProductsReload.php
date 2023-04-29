<?php

namespace App\Eloquent\Shops\Products;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ShopProductsReload extends Model
{
    protected $guarded = array();

    public function user()
    {
        return $this->hasOne('App\User', 'id', 'user_id');
    }

    public function reloadedUser()
    {
        return $this->hasOne('App\User', 'id', 'reloaded_user_id');
    }

    public function getReloadedTimeAttribute()
    {
        return Carbon::parse($this->reloaded_datetime)->setTimezone('Europe/Moscow')->toDateTimeString();
    }

    public function getUpdatedDatetimeAttribute()
    {
        return Carbon::parse($this->updated_at)->setTimezone('Europe/Moscow')->toDateTimeString();
    }
}
