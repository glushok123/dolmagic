<?php

namespace App\Eloquent\Warehouse;

use App\Observers\WarehouseMovementProductObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class WarehouseMovementProduct extends Model
{
    protected $guarded = [];
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        static::observe(new WarehouseMovementProductObserver());
    }

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'id', 'product_id');
    }

    public function movement()
    {
        return $this->belongsTo('App\Eloquent\Warehouse\WarehouseMovement', 'warehouse_movement_id', 'id');
    }

    public function warehouse()
    {
        return $this->hasOne('App\Eloquent\Warehouse\Warehouse', 'id', 'warehouse_id');
    }
    public function toWarehouse()
    {
        return $this->hasOne('App\Eloquent\Warehouse\Warehouse', 'id', 'to_warehouse_id');
    }

    public function getCreatedTimeAttribute()
    {
        return Carbon::parse($this->created_at)->setTimezone('Europe/Moscow')->format('Y-m-d H:i:s');
    }
    public function getUpdatedTimeAttribute()
    {
        return Carbon::parse($this->updated_at)->setTimezone('Europe/Moscow')->format('Y-m-d H:i:s');
    }

    public function getBalanceAttribute($warehouseIds)
    {
        $balance = 0;
        switch($this->movement->type)
        {
            case -1:
                    $balance += -1 *$this->product_quantity;
                break;
            case 0:
                if($warehouseIds and in_array($this->warehouse_id, $warehouseIds))
                        $balance += -1 *$this->product_quantity;

                if($warehouseIds and in_array($this->to_warehouse_id, $warehouseIds))
                    $balance += 1 *$this->product_quantity;


                break;
            case 1:
                    $balance += 1 *$this->product_quantity;
                break;
        }

        return $balance;
    }





}
