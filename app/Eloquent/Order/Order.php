<?php

namespace App\Eloquent\Order;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public function info()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersInfo');
    }

    public function system()
    {
        return $this->hasOne('App\Eloquent\System\System', 'id', 'system_id');
    }

    public function products()
    {
        return $this->hasMany('App\Eloquent\Order\OrdersProduct', 'order_id', 'id');
    }

    public function packs()
    {
        return $this->hasMany('App\Eloquent\Order\OrdersProductsPack', 'order_id', 'id');
    }

    public function updates()
    {
        return $this->hasMany('App\Eloquent\Order\OrdersUpdate')->orderBy('created_at', 'DESC')->orderBy('type_id', 'DESC');
    }

    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'order_id', 'id');
    }

    public function defaultWarehouse()
    {
        return $this->hasOne('App\Eloquent\Warehouse\Warehouse', 'id', 'default_warehouse_id');
    }

    /*
    public function typeShop()
    {
        return $this
            ->hasOne('App\Eloquent\Order\OrdersTypeShop', 'system_id', 'system_id')
            ->where('warehouse_id', '=', $this->default_warehouse_id);
    }
    */

    public function typeShop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'type_shop_id');
    }

    public function shop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'type_shop_id');
    }

    public function cancellationRequest()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersCancellationRequest', 'order_id', 'id')
            ->where('shop_id', $this->type_shop_id);
    }




    // GET

    public function getStrPadIdAttribute()
    {
        return str_pad($this->id,5,'0',STR_PAD_LEFT);
    }

    public function getEditPathAttribute()
    {
        return route('orders.edit', ['id' => $this->id]);
    }

    public function getOrderURLAttribute()
    {
        $url = '';
        if(isset($this->typeShop))
        {
            if($this->typeShop->order_url) $url = $this->typeShop->order_url;
        }

        if(!$url)
        {
            if(isset($this->system))
            {
                if($this->system->order_url) $url = $this->system->order_url;
            }
        }

        return $url;
    }
}
