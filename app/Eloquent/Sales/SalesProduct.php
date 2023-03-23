<?php

namespace App\Eloquent\Sales;

use App\Eloquent\Warehouse\WarehouseMovementProduct;
use App\Eloquent\Warehouse\WarehouseProductsAmount;
use Illuminate\Database\Eloquent\Model;
use App\Observers\Sale\SaleProductObserver;
use Illuminate\Support\Facades\DB;

class SalesProduct extends Model
{
    protected $guarded = array();

    protected $casts = [
        'commission_value' => 'float',
        'commission_percent' => 'float',
        'commission_deduction_value' => 'float',
        'commission_min_value' => 'float',

        'product_price' => 'float',
        'purchase_price' => 'float',
        'product_spp' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();
        static::observe(new SaleProductObserver());
    }

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'id', 'product_id');
    }

    public function pack()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesProductsPack', 'id', 'pack_id');
    }

    public function status()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesProductsStatus', 'id', 'status_id');
    }

    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'id', 'sale_id');
    }

    public function statusHistories()
    {
        return $this->hasMany('App\Eloquent\Sales\SalesProductsStatusesHistory', 'sales_product_id', 'id')->orderBy('created_at');
    }




    public function getTotalWeightAttribute()
    {
        return $this->product->weight * $this->product_quantity;
    }

    public function getTotalEstimatedWeightAttribute()
    {
        return $this->product->BoxSizes->estimatedWeight * $this->product_quantity;
    }

    public function getTotalVolumeWeightAttribute()
    {
        return $this->product->BoxSizes->volumeWeightRounded * $this->product_quantity;
    }

    public function getTotalClearEstimatedWeightAttribute()
    {
        return $this->product->BoxSizes->clearEstimatedWeight * $this->product_quantity;
    }

    public function getTotalPurchasePriceAttribute()
    {
        return $this->purchase_price * $this->product_quantity;
    }

    public function getTotalPriceAttribute()
    {
        return $this->product_price * $this->product_quantity;
    }

    public function getTotalPriceWithoutCommissionAttribute()
    {
        if($this->status->is_return_status) return 0;

        return $this->TotalPrice - ($this->commission_value * $this->product_quantity);
    }

    public function getPostingBalanceAttribute()
    {

        $countWriteOff = WarehouseMovementProduct::where('product_id', $this->product_id)
            ->whereHas('movement', function($movement){
                $movement
                    ->where('type', -1)
                    ->where('sale_id', $this->sale_id)
                    ->where('posting', 1);
            })->sum('product_quantity');

        $countPosting = WarehouseMovementProduct::where('product_id', $this->product_id)
            ->whereHas('movement', function($movement){
                $movement
                    ->where('type', 1)
                    ->where('sale_id', $this->sale_id)
                    ->where('posting', 1);
            })->sum('product_quantity');

        return $countWriteOff - $countPosting;
    }

    public function getCanReturnStocksAttribute()
    {
        $canReturnStocks = false;

        if($this->PostingBalance > 0)
            $canReturnStocks = true;

        return $canReturnStocks;
    }

    public function getLastMovementWarehouseIdAttribute()
    {
        $WarehouseMovementProduct = WarehouseMovementProduct::where([
            'product_id' => $this->product_id,
        ])->whereHas('movement', function($movement){
            $movement
                ->where('type', -1)
                ->where('sale_id', $this->sale_id)
                ->where('posting', 1);
        })->orderBy('created_at', 'DESC')->first();

        if($WarehouseMovementProduct)
        {
            return $WarehouseMovementProduct->warehouse_id;
        }

        return false;
    }

    public function getLastMovementPriceAttribute()
    {
        $WarehouseMovementProduct = WarehouseMovementProduct::where([
            'product_id' => $this->product_id,
        ])->whereHas('movement', function($movement){
            $movement
                ->where('type', -1)
                ->where('sale_id', $this->sale_id)
                ->where('posting', 1);
        })->orderBy('created_at', 'DESC')->first();

        if($WarehouseMovementProduct)
        {
            return $WarehouseMovementProduct->product_price;
        }

        return false;
    }

    public function getWarehousesAvailablesAttribute()
    {
        $quantity = 0;
        $warehouseMovementProductsWarehousesIds = WarehouseMovementProduct::where([
            ['product_id', $this->product_id],
            ['posting', 1],
        ])->whereHas('movement', function($movement)
        {
            $movement->where([
                ['posting', 1],
                ['type', -1],
                ['sale_id', $this->sale_id],
            ]);
        })->pluck('warehouse_id')->toArray();

        if($warehouseMovementProductsWarehousesIds)
        {
            $quantity = WarehouseProductsAmount
                ::whereIn('warehouse_id', $warehouseMovementProductsWarehousesIds)
                ->where('product_id', $this->product_id)
                ->sum('available');
        }

        return $quantity;
    }

    public function getWarehousesBalancesAttribute()
    {
        $quantity = 0;
        $warehouseMovementProductsWarehousesIds = WarehouseMovementProduct::where([
            ['product_id', $this->product_id],
            ['posting', 1],
        ])->whereHas('movement', function($movement)
        {
            $movement->where([
                ['posting', 1],
                ['type', -1],
                ['sale_id', $this->sale_id],
            ]);
        })->pluck('warehouse_id')->toArray();

        if($warehouseMovementProductsWarehousesIds)
        {
            $quantity = WarehouseProductsAmount
                ::whereIn('warehouse_id', $warehouseMovementProductsWarehousesIds)
                ->where('product_id', $this->product_id)
                ->sum(DB::raw('available - reserved'));

            $quantity = (int) $quantity;
        }

        return $quantity;
    }



}
