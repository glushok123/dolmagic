<?php

namespace App\Observers\Sale;

use App\Eloquent\Sales\SalesCost;
use App\Models\Sales;
use App\Models\Users\Notifications;
use App\Models\Users\Users;
use Carbon\Carbon;


class SaleCostObserver
{
    public $historyPrefix = 'sale-cost-';

    public function creating(SalesCost $SalesCost)
    {
        //$SalesCost->last_user_id = Users::getCurrent()->id??0; it's sooo wrong
    }

    public function updating(SalesCost $SalesCost)
    {
        /* it's sooo wrong
        if(!$SalesCost->last_user_id)
        {
            if($SalesCost->value != $SalesCost->getOriginal('value'))
            {
                $SalesCost->last_user_id = Users::getCurrent()->id??0;
            }
        }
        */
    }

    public function created(SalesCost $SalesCost)
    {
        Sales::historyAdd($SalesCost->sale_id, $SalesCost, $this->historyPrefix.'created');
    }

    public function updated(SalesCost $SalesCost)
    {
        $changes = $SalesCost->isDirty() ? $SalesCost->getDirty() : false;
        if($changes)
        {
            foreach($changes as $attr => $value)
            {
                Sales::historyAdd($SalesCost->sale_id, $SalesCost, $this->historyPrefix.$attr, $SalesCost->getOriginal($attr), $SalesCost->$attr);
            };
        };
    }

    public function deleted(SalesCost $SalesCost)
    {
        Sales::historyAdd($SalesCost->sale_id, $SalesCost, $this->historyPrefix.'deleted');
    }
}
