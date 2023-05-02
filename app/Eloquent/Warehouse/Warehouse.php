<?php

namespace App\Eloquent\Warehouse;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    public $timestamps = false;

    public function amounts($productId = false)
    {
        return $this->hasMany('App\Eloquent\Warehouse\WarehouseProductsAmount', 'warehouse_id', 'id');
    }

    public function getClassAttribute()
    {
        $statusClass = '';
        switch($this->attributes['state']){
            case -1:
                $statusClass = ' table-danger';
                break;
            case 0:
                $statusClass = ' table-warning';
                break;
            case 1:

                break;
            case 2:
                $statusClass = ' table-success';
                break;
        };
        return $statusClass;
    }

    public function getEditPathAttribute()
    {
        return route('warehouse.edit', ['id' => $this->id]);
    }

    public function getLogoAttribute()
    {
        return mb_substr($this->location, 0, 1);
    }
}
