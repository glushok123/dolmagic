<?php

namespace App\Eloquent\Products;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ProductsTempDiscountFile extends Model
{
    protected $guarded = array();

    public function shop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'shop_id');
    }

    public function user()
    {
        return $this->hasOne('App\User', 'id', 'user_id');
    }

    public function values()
    {
        return $this->hasMany('App\Eloquent\Products\ProductsTempDiscountFileValue', 'file_id', 'id');
    }

    public function getDownloadUrlAttribute()
    {
        return asset('/storage/files/wildberries/actions/'.$this->filename);
    }


    public function getStateAttribute()
    {
        $state = new \stdClass();
        $state->class = '';
        $state->value = '';

        $Now = Carbon::now();
        $periodFrom = Carbon::parse($this->period_from, 'Europe/Moscow')->setTimezone('UTC');
        $periodTo = Carbon::parse($this->period_to, 'Europe/Moscow')->setTimezone('UTC');

        if(($Now > $periodTo))
        {
            $state->class = 'badge-primary';
            $state->value = 'Акция уже прошла';
            return $state;
        }

        if($this->active)
        {
            if(($Now >= $periodFrom) and ($Now <= $periodTo))
            {
                $state->class = 'badge-success';
                $state->value = 'Активированы';
            }else
            {
                $state->class = 'badge-warning';
                $state->value = 'Ожидают периода действия';
            }
        }else
        {
            $state->class = 'badge-danger';
            $state->value = 'Не активированы';
        }

        return $state;
    }



}
