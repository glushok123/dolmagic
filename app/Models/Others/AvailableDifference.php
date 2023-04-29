<?php

namespace App\Models\Others;

use App\Console\Api\OzonFBOApi;
use App\Console\Api\YandexApi;
use App\Eloquent\Order\OrdersTypeShop;
use App\Eloquent\Products\Product;
use App\Eloquent\Products\ProductsAvailableCrm;
use App\Eloquent\Products\ProductsAvailableDifference;
use App\Eloquent\Warehouse\TypeShopWarehouse;
use App\Models\Model;
use App\Models\Products;
use Carbon\Carbon;

class AvailableDifference extends Model{

    public static function yandexAvailableDifference($shopId)
    {
        $YandexAPI = Yandex::getApiByShopId($shopId);
        $products = Product::where([['state', '>=', 0]])->get();

        $skus = [];
        foreach($products as $Product)
        {
            $Product->available = $Product->shopAmounts($shopId)->amounts->available;
            $skus[] = $Product->sku;
        }

        $yandexStats = $YandexAPI->getStatsSkus($skus);

        if(count($yandexStats) === 0)
        {
            $comment = 'No one product got';
            self::log('error', 'yandexFBYAvailableDifference', $comment);
            return($comment);
        }

        $differenceArray = [];

        foreach($products as $Product)
        {
            $Product->shopAvailable = 0;
            $Product->shopDefect = 0;
            $Product->shopExpired = 0;
            $Product->shopFit = 0;
            $Product->shopFreeze = 0;
            $Product->shopQuarantine = 0;
            $Product->shopUtilization = 0;
            $Product->shopSuggest = 0;
            $Product->shopTransit = 0;

            foreach($yandexStats as $yandexKeyStat => $YandexStat)
            {
                if($Product->sku === $YandexStat->shopSku)
                {
                    if(isset($YandexStat->warehouses))
                    {

                        foreach($YandexStat->warehouses[0]->stocks as $YandexWarehouseStock)
                        {
                            switch ($YandexWarehouseStock->type)
                            {
                                case 'AVAILABLE':
                                    $Product->shopAvailable = $YandexWarehouseStock->count;
                                    break;
                                case 'DEFECT':
                                    $Product->shopDefect = $YandexWarehouseStock->count;
                                    break;
                                case 'EXPIRED':
                                    $Product->shopExpired = $YandexWarehouseStock->count;
                                    break;
                                case 'FIT':
                                    $Product->shopFit = $YandexWarehouseStock->count;
                                    break;
                                case 'FREEZE':
                                    $Product->shopFreeze = $YandexWarehouseStock->count;
                                    break;
                                case 'QUARANTINE':
                                    $Product->shopQuarantine = $YandexWarehouseStock->count;
                                    break;
                                case 'UTILIZATION':
                                    $Product->shopUtilization = $YandexWarehouseStock->count;
                                    break;
                                case 'SUGGEST':
                                    $Product->shopSuggest = $YandexWarehouseStock->count;
                                    break;
                                case 'TRANSIT':
                                    $Product->shopTransit = $YandexWarehouseStock->count;
                                    break;
                            }
                        }
                    }

                    if(($Product->available !== $Product->shopAvailable)
                        or ($Product->shopQuarantine > 0)
                        or ($Product->shopDefect > 0))
                    {
                        $differenceArray[] = $Product;
                    }
                    unset($YandexStat->warehouses[0]->stocks[$yandexKeyStat]);
                }
            }
        }

        foreach($differenceArray as $DifferenceProduct)
        {
            $ProductsAvailableDifference = new ProductsAvailableDifference;
            $ProductsAvailableDifference->shop_id = $shopId;
            $ProductsAvailableDifference->product_id = $DifferenceProduct->id;
            $ProductsAvailableDifference->crm_available = $DifferenceProduct->available;

            $ProductsAvailableDifference->shop_available = $DifferenceProduct->shopAvailable;

            $ProductsAvailableDifference->shop_defect = $DifferenceProduct->shopDefect;
            $ProductsAvailableDifference->shop_expired = $DifferenceProduct->shopExpired;
            $ProductsAvailableDifference->shop_fit = $DifferenceProduct->shopFit;
            $ProductsAvailableDifference->shop_freeze = $DifferenceProduct->shopFreeze;
            $ProductsAvailableDifference->shop_quarantine = $DifferenceProduct->shopQuarantine;
            $ProductsAvailableDifference->shop_utilization = $DifferenceProduct->shopUtilization;
            $ProductsAvailableDifference->shop_suggest = $DifferenceProduct->shopSuggest;
            $ProductsAvailableDifference->shop_transit = $DifferenceProduct->shopTransit;

            $ProductsAvailableDifference->save();
        }
    }

    public static function ozonStvFboAvailableDifference()
    {
        $OzonFBOApi = new OzonFBOApi('Stavropol');
        $shopId = $OzonFBOApi->shopId;
        $shopIds = array_unique(OrdersTypeShop::where('parent_shop_id', $shopId)->orWhere('id', $shopId)->pluck('id')->toArray());
        $typeShopWarehousesIds = array_unique(TypeShopWarehouse::whereIn('type_shop_id', $shopIds)->pluck('warehouse_id')->toArray());

        $products = Product::where([['state', '>=', 0]])->get();

        foreach($products as $Product)
        {
            $Product->totalAvailable = 0;

            foreach($typeShopWarehousesIds as $TypeShopWarehouseId)
            {
                if($available = $Product->getWarehouseAvailable($TypeShopWarehouseId))
                {
                    $Product->totalAvailable += $available;
                }
            }
        }
        $ozonStocks = $OzonFBOApi->getStocksV3();

        if(count($ozonStocks) === 0)
        {
            $comment = 'No one product got';
            self::log('error', 'ozonStvFboAvailableDifference', $comment);
            return($comment);
        }

        $differenceArray = [];
        foreach($products as $Product)
        {
            $addToArray = false;
            $Product->shopAvailable = 0;
            $Product->shopFreeze = 0;

            foreach($ozonStocks as $ozonKeyStock => $OzonStock)
            {
                if($Product->sku === $OzonStock->offer_id)
                {
                    foreach($OzonStock->stocks as $OSStock)
                    {
                        if($OSStock->type === 'fbo')
                        {
                            $Product->shopAvailable += $OSStock->present??0;
                            $Product->shopFreeze += $OSStock->reserved??0;
                        }
                    }

                    if($Product->totalAvailable !== $Product->shopAvailable) $addToArray = true;

                    unset($ozonStocks[$ozonKeyStock]);
                    break;
                }
            }

            if($addToArray or ($Product->totalAvailable > 0))
                $differenceArray[] = $Product;
        }


        foreach($ozonStocks as $OzonStock)
        {
            $shopAvailable = 0;
            $shopFreeze = 0;
            foreach($OzonStock->stocks as $OSStock)
            {
                if($OSStock->type === 'fbo')
                {
                    $shopAvailable += $OSStock->present??0;
                    $shopFreeze += $OSStock->reserved??0;
                }
            }

            if(($shopAvailable > 0) or ($shopFreeze > 0))
            {
                $Product = Products::getProductBy('sku', $OzonStock->offer_id, true, 'Ozon различия');
                $Product->shopAvailable = $shopAvailable;
                $Product->shopFreeze = $shopFreeze;
                $differenceArray[] = $Product;
            }
        }


        foreach($differenceArray as $DifferenceProduct)
        {
            $ProductsAvailableDifference = new ProductsAvailableDifference;
            $ProductsAvailableDifference->shop_id = $shopId;
            $ProductsAvailableDifference->product_id = $DifferenceProduct->id;
            $ProductsAvailableDifference->crm_available = $DifferenceProduct->totalAvailable;
            $ProductsAvailableDifference->shop_available = $DifferenceProduct->shopAvailable;
            $ProductsAvailableDifference->shop_freeze = $DifferenceProduct->shopFreeze;
            if($ProductsAvailableDifference->save())
            {
                // add info warehouses
                foreach($typeShopWarehousesIds as $TypeShopWarehouseId)
                {
                    if($available = $DifferenceProduct->getWarehouseAvailable($TypeShopWarehouseId))
                    {
                        $ProductsAvailableCrm = new ProductsAvailableCrm;
                        $ProductsAvailableCrm->products_available_difference_id = $ProductsAvailableDifference->id;
                        $ProductsAvailableCrm->shop_id = $shopId;
                        $ProductsAvailableCrm->warehouse_id = $TypeShopWarehouseId;
                        $ProductsAvailableCrm->available = $available;
                        $ProductsAvailableCrm->save();
                    }
                }
            }
        }
    }
}


