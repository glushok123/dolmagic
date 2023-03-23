<?php

namespace App\Observers\Sale;

use App\Eloquent\Sales\SalesIncome;
use App\Models\Sales;
use Carbon\Carbon;


class SaleIncomeObserver
{
    public $historyPrefix = 'sale-income-';

    public function created(SalesIncome $SalesIncome)
    {
        Sales::historyAdd($SalesIncome->sale_id, $SalesIncome, $this->historyPrefix.'created');
    }

    public function updated(SalesIncome $SalesIncome)
    {
        $changes = $SalesIncome->isDirty() ? $SalesIncome->getDirty() : false;
        if($changes)
        {
            foreach($changes as $attr => $value)
            {
                Sales::historyAdd($SalesIncome->sale_id, $SalesIncome, $this->historyPrefix.$attr, $SalesIncome->getOriginal($attr), $SalesIncome->$attr);
            };
        };
    }

    public function deleted(SalesIncome $SalesIncome)
    {
        Sales::historyAdd($SalesIncome->sale_id, $SalesIncome, $this->historyPrefix.'deleted');
    }
}
