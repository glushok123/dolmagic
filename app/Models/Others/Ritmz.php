<?php

namespace App\Models\Others;

use App\Console\Api\RitmzApi;
use App\Eloquent\Order\Order;
use App\Eloquent\Other\RitmzDifference;
use App\Eloquent\Products\Product;
use App\Eloquent\Sales\Sale;
use App\Models\Model;
use App\Models\Products;
use Carbon\Carbon;
use function foo\func;

class Ritmz extends Model
{
    public static function getProductsDifferences()
    {
        $warehouseId = 2; // 2 = MSK
        $ritmzProducts = RitmzApi::getRemains();
        $availableProducts = Product::whereHas('amounts', function ($amounts) use ($warehouseId)
        {
            $amounts->where('available', '>', 0);
            $amounts->where('warehouse_id', $warehouseId);
        })->get();

        foreach($ritmzProducts as $RitmzProduct)
        {
            $RitmzDifference = new RitmzDifference;
            $RitmzDifference->product_sku = $RitmzProduct->item->id;
            $RitmzDifference->ritmz_quantity = $RitmzProduct->quantity;
            $save = false;

            if($Product = Products::getProductBy('sku', $RitmzProduct->item->id))
            {
                $available = $Product->getWarehouseAvailable($warehouseId);
                if($available !== $RitmzProduct->quantity)
                {
                    $RitmzDifference->crm_quantity = $available;
                    $save = true;
                }
            }else{
                $save = true;
            }

            if($save) $RitmzDifference->save();

            foreach($availableProducts as $key => $AvailableProduct)
            {
                if($AvailableProduct->sku === $RitmzDifference->product_sku)
                    unset($availableProducts[$key]);
            }
        }

        foreach($availableProducts as $AvailableProduct)
        {
            $available = $AvailableProduct->getWarehouseAvailable($warehouseId);

            $RitmzDifferenceNonexistent = new RitmzDifference;
            $RitmzDifferenceNonexistent->product_sku = $AvailableProduct->sku;
            $RitmzDifferenceNonexistent->crm_quantity = $available;
            $RitmzDifferenceNonexistent->save();
        }
    }

    public static function saveOrdersNumbers()
    {
        $RitmzApi = new RitmzApi();

        if($ritmzOrders = $RitmzApi->getOrders(Carbon::now()->subDays(5)))
        //if($ritmzOrders = $RitmzApi->getOrders('2022-05-20 00:00:00', '2022-05-25 23:59:59'))
        {
            $count = count($ritmzOrders);
            foreach($ritmzOrders as $key => $RitmzOrder)
            {
                dump("{$key} of $count");

                $Order = Order::where('system_order_id', $RitmzOrder->id)
                    ->orWhere('system_order_id', $RitmzOrder->num_express)
                    ->first();

                if($Order)
                {
                    $Order->info->ritmz_number = $RitmzOrder->rz_num;
                    $Order->info->save();
                }
            }
        }
    }
}


