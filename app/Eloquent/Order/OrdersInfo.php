<?php

namespace App\Eloquent\Order;

use App\Observers\Order\OrdersInfoObserver;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OrdersInfo extends Model
{
    // Options
    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();
        static::observe(new OrdersInfoObserver());
    }

    // Relationship

    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'order_id', 'order_id');
    }

    public function order()
    {
        return $this->hasOne('App\Eloquent\Order\Order', 'id', 'order_id');
    }

    public function paymentType()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersPaymentsType', 'id', 'payment_type_id');
    }

    public function status()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersStatus', 'id', 'order_status_id');
    }

    public function systemStatus()
    {
        return $this->hasOne('App\Eloquent\System\SystemsOrdersStatus', 'id', 'systems_orders_status_id');
    }



    public function manager()
    {
        return $this->hasOne('App\User', 'id', 'manager_id');
    }

    public function type()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersType', 'id', 'type_id');
    }

    // Mutators
    // SET
    /*
    public function setOrderDateCreateAttribute($value)
    {
        if(is_string($value)){
            $value = strtotime($value);
        };

        return $value;
    }
    */

    // GET

    public function getDateCreateTextAttribute()
    {
        $date = Carbon::createFromTimeString($this->order_date_create, 'UTC')->setTimezone('Europe/Moscow');
        return $date->format('d.m.Y H:i');
    }
    public function getOrderDateCreateTextAttribute()
    {
        $date = Carbon::createFromTimeString($this->order_date_create);
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

    public function getOrderOnlyDateCreateTextAttribute()
    {
        $date = Carbon::createFromTimeString($this->order_date_create);
        $date->setTimezone('Europe/Moscow');
        return $date->format('d.m.y');;
    }

    public function getPriceItemsAttribute($value)
    {
        return number_format($value, 2, '.', ' ');
    }

    public function getPriceTotalAttribute($value)
    {
        return number_format($value, 2, '.', ' ');
    }


    /* Products count in filter and any */
    public function productsQuantity()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersProduct', 'order_id', 'order_id')
            ->selectRaw('order_id, sum(product_quantity) as aggregate')
            ->groupBy('order_id');
    }

    public function getProductsQuantityAttribute()
    {
        // if relation is not loaded already, let's do it first
        if ( ! array_key_exists('productsQuantity', $this->relations))
            $this->load('productsQuantity');

        $related = $this->getRelation('productsQuantity');

        // then return the count directly
        return ($related) ? (int) $related->aggregate : 0;
    }

    public function productsTotalPrice()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersProduct', 'order_id', 'order_id')
            ->selectRaw('order_id, sum(product_price * product_quantity) as aggregate')
            ->groupBy('order_id');
    }

    public function getProductsTotalPriceAttribute()
    {
        // if relation is not loaded already, let's do it first
        if ( ! array_key_exists('productsTotalPrice', $this->relations))
            $this->load('productsTotalPrice');

        $related = $this->getRelation('productsTotalPrice');

        // then return the count directly
        return ($related) ? (int) $related->aggregate : 0;
    }

    public function deliveryTotalPrice()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersProductsPack', 'order_id', 'order_id')
            ->selectRaw('order_id, sum(delivery_price) as aggregate')
            ->groupBy('order_id');
    }

    public function getDeliveryTotalPriceAttribute()
    {
        // if relation is not loaded already, let's do it first
        if ( ! array_key_exists('deliveryTotalPrice', $this->relations))
            $this->load('deliveryTotalPrice');

        $related = $this->getRelation('deliveryTotalPrice');

        // then return the count directly
        return ($related) ? (int) $related->aggregate : 0;
    }
}
