<?php

namespace App\Observers\Sale;

use App\Console\Api\OzonApi;
use App\Eloquent\Sales\SalesProductsPack;
use App\Models\Others\Ozon;
use App\Models\Sales;
use Carbon\Carbon;


class SaleProductsPackObserver
{
    public function creating(SalesProductsPack $SalesProductsPack)
    {
        $Sale = $SalesProductsPack->sale;
        $SaleOrder = $Sale->order??false;
        if($SaleOrder and ($Sale->system_id === 3) and !empty($SaleOrder->system_order_id)) //Ozon
        {
            $SalesProductsPack->carrier_id = 8; // "Собственный курьер"

            //  get departure_date
            if(in_array($SaleOrder->type_shop_id, [1, 2, 10001, 10002]))
            {
                $OzonApi = Ozon::getOzonApiByShopId($SaleOrder->type_shop_id);
                $OzonOrder = $OzonApi->getOrder($SaleOrder->system_order_id);
                if($OzonOrder)
                    $SalesProductsPack->departure_date = Carbon::parse($OzonOrder->shipment_date, 'Europe/Moscow')->format('Y-m-d');
            }else
            {
                $SalesProductsPack->departure_date = Carbon::parse($Sale->created_at)->setTimezone('Europe/Moscow')->format('Y-m-d');
            }
        }

        // Yandex FBY
        if($SaleOrder and $SaleOrder->system_id === 68 and !empty($SaleOrder->system_order_id))
        {
            $SalesProductsPack->carrier_id = 9; // "Разное"

            if($SaleOrder->info and !empty($SaleOrder->info->order_date_create))
            {
                $SalesProductsPack->departure_date = Carbon::parse($SaleOrder->info->order_date_create)->setTimezone('Europe/Moscow');
            }
        }

        // Shop 1
        if($SaleOrder and $SaleOrder->system_id === 5 and !empty($SaleOrder->system_order_id))
        {
            if(empty($SalesProductsPack->carrier_id))
                $SalesProductsPack->carrier_id = 10; // "Ставрополь"

            if(empty($SalesProductsPack->departure_date))
                $SalesProductsPack->departure_date = $Sale->created_at;
        }

        // FBS
        if($SaleOrder and (in_array($SaleOrder->type_shop_id, [73, 176])))
        {
            if(empty($SalesProductsPack->carrier_id))
                $SalesProductsPack->carrier_id = 8; // "Собственный курьер"

            if(empty($SalesProductsPack->departure_date)
                and isset($SaleOrder->info->shipment_date)
                and !empty($SaleOrder->info->shipment_date)
            ) $SalesProductsPack->departure_date = $SaleOrder->info->shipment_date;
        }

        // WB
        if($Sale and in_array($Sale->type_shop_id, [177, 178, 179, 180]))
        {
            if(empty($SalesProductsPack->carrier_id))
                $SalesProductsPack->carrier_id = 8; // "Собственный курьер"

            if(empty($SalesProductsPack->departure_date))
                $SalesProductsPack->departure_date = $Sale->created_at;
        }
    }


    public $historyPrefix = 'sale-pack-';

    public function created(SalesProductsPack $SalesProductsPack)
    {
        Sales::historyAdd(
            $SalesProductsPack->sale_id,
            $SalesProductsPack,
            $this->historyPrefix.'created'
        );
    }

    public function deleted(SalesProductsPack $SalesProductsPack)
    {
        Sales::historyAdd(
            $SalesProductsPack->sale_id,
            $SalesProductsPack,
            $this->historyPrefix.'deleted'
        );
    }

    public function updated(SalesProductsPack $SalesProductsPack)
    {
        $changes = $SalesProductsPack->isDirty() ? $SalesProductsPack->getDirty() : false;
        if($changes)
        {
            foreach($changes as $attr => $value)
            {
                Sales::historyAdd(
                    $SalesProductsPack->sale_id,
                    $SalesProductsPack,
                    $this->historyPrefix.$attr,
                    $SalesProductsPack->getOriginal($attr),
                    $SalesProductsPack->$attr
                );
            };
        };
    }


}
