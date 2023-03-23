<?php

namespace App\Eloquent\Sales;

use App\Models\Sales;
use App\Observers\Sale\SaleIncomeObserver;
use Illuminate\Database\Eloquent\Model;

class SalesIncome extends Model
{
    protected $guarded = array();

    public static function boot()
    {
        parent::boot();
        static::observe(new SaleIncomeObserver());
    }

    public function income()
    {
        return $this->hasOne('App\Eloquent\Directories\Income', 'id', 'income_id');
    }

    public function getTotalValueAttribute()
    {
        return round(Sales::getIncomeOrCostValue($this), 2);
    }

}
