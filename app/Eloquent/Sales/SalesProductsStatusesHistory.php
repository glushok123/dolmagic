<?php

namespace App\Eloquent\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesProductsStatusesHistory extends Model
{
    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'id', 'sale_id');
    }

    public function saleProduct()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesProduct', 'id', 'sales_product_id');
    }

    public function oldStatus()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesProductsStatus', 'id', 'old_status_id');
    }

    public function newStatus()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesProductsStatus', 'id', 'new_status_id');
    }
}
