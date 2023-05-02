<?php

namespace App\Models;

use App\Eloquent\Order\OrdersTypeShop;
use App\Eloquent\Warehouse\TypeShopWarehouse;
use App\Eloquent\Warehouse\Warehouse;
use App\Models\Model;

class Warehouses extends Model{
    public static function getByName($name)
    {
        return Warehouse::where(\DB::raw('LOWER(abbreviation)'), '=', mb_strtolower($name))->first();
    }

    public static function getShopAmounts($Product, $typeShopId): \stdClass
    {
        $ShopAmounts = new \stdClass();
        $ShopAmounts->amounts = new \stdClass();
        $ShopAmounts->warehouses = new \stdClass();

        $ShopAmounts->amounts->available = false;
        $ShopAmounts->amounts->reserved = false;
        $ShopAmounts->amounts->arriving = false;
        $ShopAmounts->amounts->balance = false;
        $ShopAmounts->amounts->balanceWithStops = false;

        $typeShopWarehousesIds = TypeShopWarehouse::where('type_shop_id', $typeShopId)->orderBy('ordering', 'ASC')->pluck('warehouse_id')->toArray();

        if(count($typeShopWarehousesIds) > 0)
        {
            $ShopAmounts->warehouses = Warehouse::whereIn('id', $typeShopWarehousesIds)
                ->orderByRaw('FIELD (id, ' . implode(', ', $typeShopWarehousesIds) . ') ASC')
                ->get();

            $ShopAmounts->warehouses->sortBy(function($model) use ($typeShopWarehousesIds) {
                return array_search($model->getKey(), $typeShopWarehousesIds);
            });

            $ProductAmount = $Product->amounts->whereIn('warehouse_id', $typeShopWarehousesIds);
            $ShopAmounts->amounts->available = $ProductAmount->sum('available');
            $ShopAmounts->amounts->reserved = $ProductAmount->sum('reserved');
            $ShopAmounts->amounts->arriving = $ProductAmount->sum('arriving');
            $ShopAmounts->amounts->balance = $ShopAmounts->amounts->available - $ShopAmounts->amounts->reserved;

            switch($typeShopId)
            {
                case 1: // OzonSTV
                case 2: // OzonMSK
                case 10001: // OzonSTV-2
                case 10002: // OzonMSK-2
                    if($ShopAmounts->amounts->balance >= 6) $ShopAmounts->amounts->balance = 50;
                    break;
                case 6: // Tmall
                    $skus = ['738726', 'DMT58','33890','21042','22303','GBK12','98761','397612','32514','62535','98767','99167','917844','LOLPNK','DVT70','HPOPLB','LOLKITT','FFB18','FXT23','FPR56','GBK11','GJM72'];
                    if(in_array($Product->sku, $skus))
                    {
                        // 2022-05-16 - 19:57
                        if(($ShopAmounts->amounts->balance < 100) and ($ShopAmounts->amounts->balance > 5))
                        {
                            $ShopAmounts->amounts->balance = 100;
                        }
                    }
                case 8: // Aliexpress
                    //if($ShopAmounts->amounts->balance > 0) $ShopAmounts->amounts->balance = 30; 2022-04-22
                break;
            }

            // for remove zero values TEST???
            if($ShopAmounts->amounts->balance and ($ShopAmounts->amounts->balance < 0))
                $ShopAmounts->amounts->balance = 0;

            $ShopAmounts->amounts->balanceWithStops = $ShopAmounts->amounts->balance;
            $Stop = Products::getSystemsProductsStopResult($Product, $typeShopId);
            if($Stop->stock) $ShopAmounts->amounts->balanceWithStops = 0;
        }

        return $ShopAmounts;
    }

    public static function getShopWarehouses($typeShopId) // there is need more speedly function
    {
        $ids = array_unique(TypeShopWarehouse::where('type_shop_id', $typeShopId)->pluck('warehouse_id')->toArray());
        return Warehouse::whereIn('id', $ids)->get();
    }
}
