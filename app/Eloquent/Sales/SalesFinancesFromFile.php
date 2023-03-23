<?php

namespace App\Eloquent\Sales;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Observers\Sale\SalesFinancesFromFileObserver;

class SalesFinancesFromFile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price' => 'float',
    ];

    public static function boot()
    {
        parent::boot();
        static::observe(new SalesFinancesFromFileObserver());
    }

    public function service()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesFinancesService', 'id', 'service_id');
    }

    public function getDateFormatAttribute()
    {
        return Carbon::parse($this->date)->format('Y-m-d');
    }


    public function file()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesFinancesFile', 'id', 'file_id');
    }

    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'id', 'sale_id');
    }

    public function order()
    {
        return $this->hasOneThrough(
            'App\Eloquent\Order\Order',
            'App\Eloquent\Sales\Sale',
            'id',
            'order_id',
            'sale_id',
            'id'
        );
    }

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'id', 'product_id');
    }

    public function shop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'shop_id');
    }

    public function getSaleProductAttribute()
    {
        return SalesProduct::where([
            ['sale_id', $this->sale_id],
            ['product_id', $this->product_id],
        ])->first();
    }


    public function getOzonFileCalcAttribute()
    {
        return round( ($this->ozon_price_1 + $this->ozon_price_2 + $this->compare_commission_value) * $this->product_quantity, 2);
    }

    public function wbData()
    {
        return $this->hasOne('App\Eloquent\Other\WB\WbReportsData', 'id', 'wb_data_id');
    }


}
