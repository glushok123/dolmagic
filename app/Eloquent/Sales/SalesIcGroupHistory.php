<?php

namespace App\Eloquent\Sales;

use App\Models\Sales;
use App\Observers\Sale\SaleIncomeObserver;
use Illuminate\Database\Eloquent\Model;

class SalesIcGroupHistory extends Model
{
    protected $casts = [
        'value' => 'float',
    ];

    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'id', 'sale_id');
    }

    public function valueType()
    {
        return $this->hasOne('App\Eloquent\Directories\ValueType', 'id', 'value_type_id');
    }

    public function flowType()
    {
        return $this->hasOne('App\Eloquent\Directories\FlowType', 'id', 'flow_type_id');
    }

    public function user()
    {
        return $this->hasOne('App\User', 'id', 'user_id');
    }

    public function costOrIncome()
    {
        $typeName = '';
        if(!empty($this->cost_id))
        {
            return $this->hasOne('App\Eloquent\Directories\Cost', 'id', 'cost_id');
        }else if(!empty($this->income_id))
        {
            return $this->hasOne('App\Eloquent\Directories\Income', 'id', 'income_id');
        }

    }

    public function getTotalValueAttribute()
    {
        $total = 0;
        switch($this->flow_type_id)
        {
            case 1:
                if($this->value_type_id === 1)
                {
                    $total = $this->value;
                }else{
                    $total = Sales::getTotalSalePrice($this->sale, false)*$this->value/100;
                }
                break;
            case 2:
                $total = Sales::calcTotalValueIfEachProduct($this->value_type_id, $this->value, $this->sale->products);
                break;
        };

        return $total;
    }


}
