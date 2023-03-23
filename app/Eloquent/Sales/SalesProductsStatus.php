<?php

namespace App\Eloquent\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesProductsStatus extends Model
{
    public function getEditPathAttribute()
    {
        return route('sale-product-statuses.edit', ['id' => $this->id]);
    }

    public function marginCalcs()
    {
        return $this->hasMany('App\Eloquent\System\SystemsMarginCalcByStatus', 'sales_products_status_id', 'id');
    }

    public function marginCalc($typeShopId)
    {
        return $this->marginCalcs->where('type_shop_id', $typeShopId)->first();
    }

    public function costsCalcs()
    {
        return $this->hasMany('App\Eloquent\System\SystemsMarginCalcCostsByStatus', 'sales_products_status_id', 'id');
    }

    public function incomesCalcs()
    {
        return $this->hasMany('App\Eloquent\System\SystemsMarginCalcIncomesByStatus', 'sales_products_status_id', 'id');
    }
}
