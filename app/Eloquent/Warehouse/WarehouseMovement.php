<?php

namespace App\Eloquent\Warehouse;

use App\Observers\WarehouseMovementObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class WarehouseMovement extends Model
{
    protected $fillable = ['order_id'];

    /**
     * Bootstrap any application services.
     *
     * @return void
     */

    protected static function boot()
    {
        parent::boot();
        static::observe(new WarehouseMovementObserver());
    }

    public function products()
    {
        return $this->hasMany('App\Eloquent\Warehouse\WarehouseMovementProduct');
    }

    public function getWarehouses()
    {
        $warehouses = new \stdClass();
        $warehouses->warehousesIds = [];
        $warehouses->toWarehousesIds = [];

        foreach($this->products as $MovementProduct)
        {
            if(!empty($MovementProduct->warehouse_id) and !in_array($MovementProduct->warehouse_id, $warehouses->warehousesIds))
                $warehouses->warehousesIds[] = $MovementProduct->warehouse_id;
            if(!empty($MovementProduct->to_warehouse_id) and !in_array($MovementProduct->to_warehouse_id, $warehouses->toWarehousesIds))
                $warehouses->toWarehousesIds[] = $MovementProduct->to_warehouse_id;
        }

        $warehouses->warehouses = Warehouse::whereIn('id', $warehouses->warehousesIds)->get();
        $warehouses->toWarehouses = Warehouse::whereIn('id', $warehouses->toWarehousesIds)->get();

        return $warehouses;
    }

    public function order()
    {
        return $this->hasOne('App\Eloquent\Order\Order', 'id', 'order_id');
    }

    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'id', 'sale_id');
    }

    public function getProductsCostAttribute()
    {
        $cost = 0;
        foreach($this->products as $product){
            $cost += $product->product_price*$product->product_quantity;
        };
        return $cost;
    }

    public function getProductsQuantityAttribute()
    {
        $quantity = 0;
        foreach($this->products as $product){
            $quantity += $product->product_quantity;
        };
        return $quantity;
    }

    public function getEditPathAttribute()
    {
        return route('warehouse.movements.edit', ['id' => $this->id]);
    }

    public function getPostingPathAttribute()
    {
        return route('warehouse.movements.posting', ['id' => $this->id]);
    }



    public function getClassAttribute()
    {
        $statusClass = '';
        switch($this->attributes['posting']){
            case 0:
                    $statusClass = ' table-warning';
                break;
            case 1:
                    $statusClass = ' table-success';
                break;
        };
        return $statusClass;
    }

    public function getPostingTitleAttribute()
    {
        $postingTitle = '';
        switch($this->attributes['posting']){
            case 0:
                    $postingTitle = 'Не проведено';
                break;
            case 1:
                    $postingTitle = 'Проведено';
                break;
        };
        return $postingTitle;
    }

    public function getTypeTitleAttribute()
    {
        $typeTitle = '';
        switch($this->attributes['type']){
            case -1:
                $typeTitle = 'Списание';
                break;
            case 0:
                $typeTitle = 'Перемещение';
                break;
            case 1:
                $typeTitle = 'Оприходование';
                break;
        };
        return $typeTitle;
    }

    // GET
    public function getDateCreateTextAttribute()
    {
        $date = Carbon::createFromTimeString($this->created_at);
        $date->setTimezone('Europe/Moscow');

        $dateNow = Carbon::now('Europe/Moscow');
        if(
            ($dateNow->day == $date->day)
            and
            ($dateNow->month == $date->month)
            and
            ($dateNow->year == $date->year)
        ){
            $dateString = 'Сегодня '. $date->format('H:i');
        }else{
            $dateString = $date->format('d.m.y H:i');
        };

        return $dateString;
    }

    public function getDateUpdateTextAttribute()
    {
        $date = Carbon::createFromTimeString($this->updated_at);
        $date->setTimezone('Europe/Moscow');

        $dateNow = Carbon::now('Europe/Moscow');
        if(
            ($dateNow->day == $date->day)
            and
            ($dateNow->month == $date->month)
            and
            ($dateNow->year == $date->year)
        ){
            $dateString = 'Сегодня '. $date->format('H:i');
        }else{
            $dateString = $date->format('d.m.y H:i');
        };

        return $dateString;
    }

    public function getDatePostingAttribute()
    {
        $dateString = '';
        if(!empty($this->posting_at)){
            $date = Carbon::createFromTimeString($this->posting_at);
            $date->setTimezone('Europe/Moscow');

            $dateString = $date->format('d.m.y H:i');
        };
        return $dateString;
    }

    public function getDatePostingTextAttribute()
    {
        $dateString = '';
        if(!empty($this->posting_at)){
            $date = Carbon::createFromTimeString($this->posting_at);
            $date->setTimezone('Europe/Moscow');
            $dateNow = Carbon::now('Europe/Moscow');

            if(
                ($dateNow->day == $date->day)
                and
                ($dateNow->month == $date->month)
                and
                ($dateNow->year == $date->year)
            ){
                $dateString = 'Сегодня '. $date->format('H:i');
            }else{
                $dateString = $date->format('d.m.y H:i');
            };
        };
        return $dateString;
    }

    public function getProductsQuantityBySkuAttribute($sku, $warehouseId = false, $toWarehouseId = false)
    {
        return WarehouseMovementProduct::where('warehouse_movement_id', $this->id)
            ->whereHas('product', function($q) use ($sku) {
            $q->where('sku', $sku);
        })->sum('product_quantity');
    }

    public function getProductsBySkuAttribute($sku, $warehouseId = false, $toWarehouseId = false)
    {
        $warehouseMovementProducts =  WarehouseMovementProduct::where('warehouse_movement_id', $this->id)
            ->whereHas('product', function($q) use ($sku)
            {
                $q->where('sku', $sku);
            });

        if($warehouseId) $warehouseMovementProducts->where('warehouse_id', $warehouseId);
        if($toWarehouseId) $warehouseMovementProducts->where('to_warehouse_id', $toWarehouseId);

        return $warehouseMovementProducts->get();
    }

    public function getMinusProductsBySkuAttribute($sku, $warehouseIds = false)
    {
        if(!in_array($this->type, [-1, 0]))
        {
            return collect();
        }else
        {
            $warehouseMovementProducts =
                WarehouseMovementProduct::where('warehouse_movement_id', $this->id)
                ->whereHas('product', function($q) use ($sku)
                {
                    $q->where('sku', $sku);
                });

            if($warehouseIds) $warehouseMovementProducts->whereIn('warehouse_id', $warehouseIds);

            return $warehouseMovementProducts->get();
        }
    }

    public function getPlusProductsBySkuAttribute($sku, $warehouseIds = false)
    {
        if(!in_array($this->type, [1, 0]))
        {
            return collect();
        }else
        {
            $warehouseMovementProducts =
                WarehouseMovementProduct::where('warehouse_movement_id', $this->id)
                    ->whereHas('product', function($q) use ($sku)
                    {
                        $q->where('sku', $sku);
                    });

            if($warehouseIds)
            {
                if($this->type === 1) $warehouseMovementProducts->whereIn('warehouse_id', $warehouseIds);
                if($this->type === 0) $warehouseMovementProducts->whereIn('to_warehouse_id', $warehouseIds);
            }

            return $warehouseMovementProducts->get();
        }
    }

    public function getBalanceBySkuAttribute($sku, $warehouseIds = false)
    {
        $balance = 0;

        $warehouseMovementProducts =
            WarehouseMovementProduct::where('warehouse_movement_id', $this->id)
                ->whereHas('product', function($q) use ($sku)
                {
                    $q->where('sku', $sku);
                });
        if($warehouseIds)
        {
            if($this->type === 1) $warehouseMovementProducts->whereIn('warehouse_id', $warehouseIds);
            if($this->type === -1) $warehouseMovementProducts->whereIn('warehouse_id', $warehouseIds);
            if($this->type === 0)
            {
                $warehouseMovementProducts->where(function($q) use ($warehouseIds)
                {
                    $q
                        ->whereIn('to_warehouse_id', $warehouseIds) //  warehouse_id ?
                        ->orWhereIn('to_warehouse_id', $warehouseIds);
                });
            }
        }

        if($warehouseMovementProducts = $warehouseMovementProducts->get()
            and ($warehouseMovementProducts->count() > 0)
        )
        {
            foreach($warehouseMovementProducts as $WarehouseMovementProduct)
            {
                $balance += $WarehouseMovementProduct->getBalanceAttribute($warehouseIds);
            }
        }

        return $balance;
    }

}
