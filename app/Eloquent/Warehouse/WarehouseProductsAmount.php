<?php

namespace App\Eloquent\Warehouse;

use Illuminate\Database\Eloquent\Model;

class WarehouseProductsAmount extends Model
{
    protected $fillable = ['warehouse_id', 'product_id'];

    protected $casts = [
        'purchase_average_price' => 'float',
        'selling_average_price' => 'float',
    ];

    public function warehouse()
    {
        return $this->hasOne('App\Eloquent\Warehouse\Warehouse', 'id', 'warehouse_id');
    }

    public function getBalanceAttribute()
    {
        $balance = $this->available - $this->reserved;

        if($balance and ($balance < 0)) $balance = 0;// for remove zero values TEST???
        return $balance;
    }
}
