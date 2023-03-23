<?php

namespace App\Observers\Sale;

use App\Eloquent\Sales\Sale;
use App\Models\Directories\Costs;
use App\Models\Sales;
use Carbon\Carbon;


class SaleObserver
{
    public $historyPrefix = 'sale-';

    public function creating(Sale $Sale)
    {
        if(empty($Sale->date_sale)) $Sale->date_sale = Carbon::now()->setTimezone('Europe/Moscow')->toDateTimeString();
    }

    public function saving(Sale $Sale)
    {
        Sales::beforeSave($Sale);
    }

    public function created(Sale $Sale)
    {
        Sales::historyAdd($Sale->id, $Sale, $this->historyPrefix.'created');
    }

    public function deleted(Sale $Sale)
    {
        Sales::historyAdd(
            $Sale->id,
            $Sale,
            $this->historyPrefix.'deleted'
        );
    }

    public function updated(Sale $Sale)
    {
        $changes = $Sale->isDirty() ? $Sale->getDirty() : false;
        if($changes)
        {
            foreach($changes as $attr => $value)
            {
                Sales::historyAdd($Sale->id, $Sale, $this->historyPrefix.$attr, $Sale->getOriginal($attr), $Sale->$attr);
            };
        };
    }
}
