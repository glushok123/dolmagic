<?php

namespace App\Eloquent\Sales;

use App\Eloquent\Order\OrdersProductsPack;
use App\Models\Sales;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Observers\Sale\SaleProductsPackObserver;


class SalesProductsPack extends Model
{
    protected $guarded = array();

    protected static function boot()
    {
        parent::boot();
        static::observe(new SaleProductsPackObserver());
    }

    public function products()
    {
        return $this->hasMany('App\Eloquent\Sales\SalesProduct', 'pack_id', 'id');
    }

    public function carrier()
    {
        return $this->hasOne('App\Eloquent\Directories\Carrier', 'id', 'carrier_id');
    }

    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'id', 'sale_id');
    }

    public function getDepartureDateTextAttribute()
    {
        $date = Carbon::createFromDate($this->departure_date);
        $date->setTimezone('Europe/Moscow');
        return $date->format('d.m.y');
    }

    public function getOrderTrackNumberAttribute()
    {
        $trackNumber = '';
        if($this->sale and $this->sale->order and $this->sale->order->packs)
        {
            if($OrderPack = $this->sale->order->packs->where('number', $this->number)->first())
            {
                $trackNumber = $OrderPack->delivery_track_number;
            }
        }
        return $trackNumber;
    }

    public function getOrderPackAttribute()
    {
        $saleId = $this->sale_id;
        return OrdersProductsPack::whereHas('order', function($q) use ($saleId)
        {
            $q->whereHas('sale', function($q2) use ($saleId)
            {
                $q2->where('id', $saleId);
            });
        })->where('number', $this->number)->first();
    }

}
