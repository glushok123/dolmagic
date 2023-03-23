<?php

namespace App\Observers\Sale;

use App\Eloquent\Sales\SalesFinancesFromFile;
use App\Models\Others\Wildberries\WildberriesReports;
use App\Models\Sales;
use App\Models\Shops\Shops;
use App\Models\Users\Notifications;
use Carbon\Carbon;


class SalesFinancesFromFileObserver
{
    public $historyPrefix = 'sale-finances-from-file-';

    public function created(SalesFinancesFromFile $SalesFinancesFromFile)
    {
        if($SalesFinancesFromFile->sale_id)
            Sales::historyAdd($SalesFinancesFromFile->sale_id, $SalesFinancesFromFile, $this->historyPrefix.'created');


            if($Sale = $SalesFinancesFromFile->sale)
            {
                if(in_array($Sale->type_shop_id, Shops::getShopIdsByType('Wildberries')))
                {
                    WildberriesReports::checkDoubleDeliveryFinances($Sale, $SalesFinancesFromFile);

                    if($SalesFinancesFromFile->is_return)
                    {
                        Notifications::notifyWBFinanceV3ReturnIncome($Sale, 'reports_finance_v3');
                    }
                }
            }
    }

    public function updated(SalesFinancesFromFile $SalesFinancesFromFile)
    {
        if($SalesFinancesFromFile->sale_id)
        {
            $changes = $SalesFinancesFromFile->isDirty() ? $SalesFinancesFromFile->getDirty() : false;
            if($changes)
            {
                foreach($changes as $attr => $value)
                {
                    Sales::historyAdd($SalesFinancesFromFile->sale_id, $SalesFinancesFromFile, $this->historyPrefix.$attr, $SalesFinancesFromFile->getOriginal($attr), $SalesFinancesFromFile->$attr);
                };
            };
        }

    }

    public function deleted(SalesFinancesFromFile $SalesFinancesFromFile)
    {
        if($SalesFinancesFromFile->sale_id)
        {
            Sales::historyAdd($SalesFinancesFromFile->sale_id, $SalesFinancesFromFile, $this->historyPrefix.'deleted');
        }

    }
}
