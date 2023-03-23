<?php

namespace App\Eloquent\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesStatusesOnCancel extends Model
{
    protected $guarded = array();

    public function saleProductsStatus()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesProductsStatus', 'id', 'sale_products_status_id');
    }

    public function systemOrderStatus()
    {
        return $this->hasOne('App\Eloquent\System\SystemsOrdersStatus', 'id', 'system_order_status_id');
    }

    public function orderTypeShop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'orders_type_shop_id');
    }

}
