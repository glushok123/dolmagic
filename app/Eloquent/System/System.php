<?php

namespace App\Eloquent\System;

use Illuminate\Database\Eloquent\Model;

class System extends Model
{
    //
    public function getSrcAttribute()
    {
        return '/images/systems/'.$this->logo;
    }

    public function cron()
    {
        return $this->hasOne('App\Eloquent\System\SystemCron', 'system_id');
    }

    public function warehouse()
    {
        return $this->hasOne('App\Eloquent\Warehouse\Warehouse', 'id', 'warehouse_id');
    }


    public function getWarehouseAvailable($warehouseId)
    {
        $available = $this
            ->hasOne('App\Eloquent\Warehouse\WarehouseProductsAmount', 'product_id', 'id')
            ->where('warehouse_id', '=', $warehouseId)
            ->first();
        $available = $available?$available->available:0;
        return $available;
    }
}
