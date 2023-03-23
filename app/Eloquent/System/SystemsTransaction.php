<?php

namespace App\Eloquent\System;

use App\Models\Systems\Transactions;
use App\Observers\System\SystemTransactionObserver;
use Illuminate\Database\Eloquent\Model;

class SystemsTransaction extends Model
{
    protected $casts = [
        'total_amount' => 'float',
        'order_amount' => 'float',
        'discount_amount' => 'float',
        'commission_amount' => 'float',
        'item_delivery_amount' => 'float',
        'item_return_amount' => 'float',
    ];

    public static function boot()
    {
        parent::boot();
        static::observe(new SystemTransactionObserver());
    }

    public function order()
    {
        return $this->hasOne('App\Eloquent\Order\Order', 'id', 'order_id');
    }

    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'id', 'sale_id');
    }

    public function fileTransactions()
    {
        return $this->hasMany(
            'App\Eloquent\System\SystemsTransactionsFromFile',
            'order_system_number',
            'order_system_number'
        )->where('type_shop_id', '=', $this->type_shop_id)
            ->where('transaction_type_id', '=', $this->transaction_type_id);
    }

    public function type()
    {
        return $this->hasOne('App\Eloquent\System\SystemsTransactionsType', 'id', 'transaction_type_id');
    }

    public function typeShop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'type_shop_id');
    }


    public function getOzonAllAmountsAttribute()
    {
        return Transactions::getOzonAllAmountsValue($this->sale, $this->transaction_date, $this);
    }

    public function getOzonOrderAmountAttribute()
    {
        return $this->ozonAllAmounts->order_amount;
    }

    public function getOzonCommissionAmountAttribute()
    {
        return $this->ozonAllAmounts->commission_amount;
    }

    public function getOzonTotalAmountAttribute()
    {
        return $this->ozonAllAmounts->total_amount;
    }

    public function getOzonItemDeliveryAmountAttribute()
    {
        return $this->ozonAllAmounts->item_delivery_amount;
    }

    public function getOzonItemReturnAmountAttribute()
    {
        return $this->ozonAllAmounts->item_return_amount;
    }

    public function getOzonDiscrepancyAttribute()
    {
        return round(round($this->total_amount, 2) - round($this->ozonTotalAmount, 2), 2);
    }



    public function getFileTransactionsQuantityAttribute()
    {
        return $this->fileTransactions->sum('quantity');
    }

    public function getFileTransactionsOrderAmountAttribute()
    {
        return $this->fileTransactions->sum('order_amount');
    }

    public function getFileTransactionsCommissionAmountAttribute()
    {
        return $this->fileTransactions->sum('commission_amount');
    }

    public function getFileTransactionsTotalAmountAttribute()
    {
        return $this->fileTransactions->sum('total_amount');
    }






}
