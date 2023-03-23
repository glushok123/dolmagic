<?php

namespace App\Eloquent\Sales;

use App\Models\Sales;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Observers\Sale\SaleCostObserver;

class SalesCost extends Model
{
    protected $guarded = array();

    protected $casts = [
        'value' => 'float',
    ];

    public static function boot()
    {
        parent::boot();
        static::observe(new SaleCostObserver());
    }

    public function cost()
    {
        return $this->hasOne('App\Eloquent\Directories\Cost', 'id', 'cost_id');
    }

    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'id', 'sale_id');
    }

    public function lastUser()
    {
        return $this->hasOne('App\User', 'id', 'last_user_id');
    }

    public function fromFiles()
    {
        return $this->hasManyThrough(
            'App\Eloquent\Sales\SalesFinancesFromFile', // service_id
            'App\Eloquent\Sales\SalesFinancesService', // id, cost_id
            'cost_id',
            'service_id',
            'cost_id',
            'id'
        )->where('sale_id', $this->sale_id);
    }

    public function getAPICostAttribute()
    {
        $costId = $this->cost_id;
        $APICost = SalesFinancesFromAPI::where([
            ['sale_id', $this->sale_id]
        ])->whereHas('service', function($q) use ($costId){
            $q->where('cost_id', $costId);
        })->first();

        return $APICost;
    }

    public function getAPICostPriceAttribute()
    {
        return $this->APICost->price??0;
    }

    public function getTotalValueAttribute()
    {
        return round(Sales::getIncomeOrCostValue($this), 2);
    }


    public function getCreatedDatetimeAttribute()
    {
        return Carbon::parse($this->created_at)->setTimezone('Europe/Moscow')->toDateTimeString();
    }

    public function getUpdatedDatetimeAttribute()
    {
        return Carbon::parse($this->updated_at)->setTimezone('Europe/Moscow')->toDateTimeString();
    }

    public function getFromFilesSumPriceAttribute()
    {
        return $this->fromFiles->sum('price');
    }

    public function getIsAutoUpdateAttribute()
    {
        if($this->last_user_id and ($this->last_user_id !== 0)) return false;
        return $this->cost->auto_update;
    }

    public function getFormulaAttribute()
    {
        return Sales::getIncomeOrCostFormula($this, $this->sale);
    }










}
