<?php

namespace App\Eloquent\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesProductsStatusesGroup extends Model
{
    public function statuses()
    {
        return $this->hasMany('App\Eloquent\Sales\SalesProductsStatus', 'sale_products_statuses_group_id', 'id')
            ->orderBy('ordering', 'ASC');
    }

    public function getEditPathAttribute()
    {
        return route('sale-product-statuses-group.edit', ['id' => $this->id]);
    }
}
