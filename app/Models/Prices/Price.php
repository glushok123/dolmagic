<?php

namespace App\Models\Prices;

use App\Eloquent\Shops\ShopUnloading;
use App\Eloquent\Shops\ShopUpOption;
use App\Models\Model;
use App\Models\Others\Unloading\Unloading;
use Carbon\Carbon;

class Price extends Model{

    public static function recalculatePriceByShopOption($price, $shopId, $ShopUpOption = false)
    {
        $price = (int) $price;
        if(!$ShopUpOption) $ShopUpOption = ShopUpOption::where('shop_id', $shopId)->first();

        if($ShopUpOption and ($price !== 0))
        {
            if($price < $ShopUpOption->importUpPriceLimit)
            {
                switch($ShopUpOption->importUpPriceLessTypeId)
                {
                    case 1: //₽
                        $price = $price + $ShopUpOption->importUpPriceLess;
                        break;
                    case 2: //%
                        $price = $price + $price*$ShopUpOption->importUpPriceLess/100;
                        break;
                }
            }else{
                switch($ShopUpOption->importUpPriceMoreTypeId)
                {
                    case 1: //₽
                        $price = $price + $ShopUpOption->importUpPriceMore;
                        break;
                    case 2: //%
                        $price = $price + $price*$ShopUpOption->importUpPriceMore/100;
                        break;
                }
            }
        }

        return (string) $price;
    }


    // V2 !!

    public static function recalculatePriceByUnloadingOption($price, $shopId): string
    {
        $price = (int) $price;

        $ShopUnloading = ShopUnloading::where([
            ['state', 1],
            ['shop_id', $shopId],
        ])->first();

        if($ShopUnloading and ($price !== 0))
            $price = Unloading::upPrice($price, $ShopUnloading);

        return (string) $price;
    }

}


