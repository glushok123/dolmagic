<?php

namespace App\Models\Others\Unloading;

use App\Eloquent\Shops\ShopUnloading;
use App\Eloquent\Shops\ShopUnloadingsProductsAddedGroup;
use App\Eloquent\Shops\ShopUnloadingsProductsGroup;
use App\Eloquent\Shops\ShopUnloadingsProductsOption;
use App\Eloquent\Shops\ShopUnloadingsUpPrice;
use App\Models\Model;
use App\Models\Products;
use Carbon\Carbon;

class Unloading extends Model{

    public static function addGroupFromForm($name, $shopUnloadingId)
    {
        $lowerName = mb_strtolower(trim($name));
        if(!$ShopUnloadingsProductsAddedGroup = ShopUnloadingsProductsAddedGroup::where('shop_unloading_id', $shopUnloadingId)->whereRaw('lower(name) = (?)', ["{$lowerName}"])->first())
        {
            $ShopUnloadingsProductsAddedGroup = new ShopUnloadingsProductsAddedGroup;
            $ShopUnloadingsProductsAddedGroup->shop_unloading_id = $shopUnloadingId;
            $ShopUnloadingsProductsAddedGroup->name = trim($name);
            $ShopUnloadingsProductsAddedGroup->save();
        }
        return $ShopUnloadingsProductsAddedGroup->id;
    }

    public static function addYMLCategories($unloadingId, &$Categories)
    {

        $shopUnloadingsProductsAddedGroups = ShopUnloadingsProductsAddedGroup::where('shop_unloading_id', $unloadingId)->get();

        foreach($shopUnloadingsProductsAddedGroups as $key => $ShopUnloadingsProductsAddedGroup)
        {
            $Category = $Categories->addChild('category', $ShopUnloadingsProductsAddedGroup->name);
            $Category['id'] = $ShopUnloadingsProductsAddedGroup->id;
            $Category['parentId'] = '5472628'; // Main group
        }
    }

    public static function getProductCategory($Product, $shopId)
    {
        if($ShopUnloading = ShopUnloading::where('shop_id', $shopId)->first())
        {
            if($ShopUnloadingsProductsOption = ShopUnloadingsProductsOption::where([
                ['product_id', $Product->id],
                ['shop_unloading_id', ($ShopUnloading->get_options_from_id?:$ShopUnloading->id)],
            ])->first())
            {
                if($ShopUnloadingsProductsOption->addedGroup) return $ShopUnloadingsProductsOption->addedGroup->name;
            }
        }

        return false;
    }

    public static function getProductTitle($Product, $shopId)
    {
        if($ShopUnloading = ShopUnloading::where('shop_id', $shopId)->first())
        {
            if($ShopUnloadingsProductsOption = ShopUnloadingsProductsOption::where([
                ['product_id', $Product->id],
                ['shop_unloading_id', ($ShopUnloading->get_options_from_id?:$ShopUnloading->id)],
            ])->first())
            {
                if($ShopUnloadingsProductsOption->new_title) return $ShopUnloadingsProductsOption->new_title;
            }
        }

        return false;
    }

    public static function changeYMLOfferProducts($unloadingId, &$Offer)
    {
        $sku = trim((string) $Offer->vendorCode);

        $Product = Products::getProductBy('sku', $sku);
        if($Product)
        {
            $ShopUnloadingsProductsOption = ShopUnloadingsProductsOption::where([
                ['product_id', $Product->id],
                ['shop_unloading_id', $unloadingId],
            ])->first();

            if($ShopUnloadingsProductsOption)
            {
                if($ShopUnloadingsProductsOption->new_title)
                    $Offer->name = $ShopUnloadingsProductsOption->new_title;

                if($ShopUnloadingsProductsOption->new_group_id)
                    $Offer->categoryId = $ShopUnloadingsProductsOption->new_group_id;
            }
        }
    }

    public static function inAssortmentChange(&$Offer)
    {
        $pos = mb_stripos((string) $Offer->name, 'в ассортименте');
        if($pos !== false)
        {
            $name = (string) $Offer->name;
            $name = str_replace(', (в ассортименте)', '', $name);
            $name = str_replace(' (в ассортименте)', '', $name);
            $name = str_replace('(в ассортименте)', '', $name);
            $name = str_replace(', в ассортименте', '', $name);
            $name = str_replace(' в ассортименте', '', $name);
            $Offer->name = $name;
            $Offer->description = (string) $Offer->description . ' Товар представлен в ассортименте.';
        }
    }


    public static function changeDescription(&$Offer)
    {
        $sku = trim((string) $Offer->vendorCode);

        $Product = Products::getProductBy('sku', $sku);
        if($Product)
        {
            $Offer->description = strip_tags(trim($Product->temp_short_description), '<br>');
        }
    }

    public static function markdownChange(&$Offer)
    {
        $sku = trim((string) $Offer->vendorCode);
        foreach($Offer->param as $param)
        {
            $paramName = (string) $param['name'];
            $paramValue = (string) $param;
            switch ($paramName)
            {
                case 'Состояние':
                    if($paramValue === 'Уценка')
                    {
                        // 1. Change name
                        $name = (string) $Offer->name;
                        $name = str_replace(' (уценка)', '', $name);
                        $name = str_replace(' (Уценка)', '', $name);
                        $name = str_replace(' уценка', '', $name);
                        $name = str_replace(' Уценка', '', $name);
                        $name = str_replace(' (уценка, упаковка)', '', $name);
                        $Offer->name = $name;

                        // 2. Set tag
                        $Offer->addChild('condition');
                        $Offer->condition['type'] = 'likenew';

                        // 3. Set comment tag
                        $Offer->condition->addChild('reason');
                        if($Product = Products::getProductBy('sku', $sku))
                        {
                            $Offer->condition->reason = strip_tags($Product->temp_full_description);
                        }
                    }
                    break;
            }
        }
    }

    public static function getPriceOption($price, $ShopUnloading)
    {
        return ShopUnloadingsUpPrice::where([
            ['shop_unloading_id', $ShopUnloading->id],
        ])->where(function($q) use ($price)
        {
            $q->where('price_min', '<=', $price)->orWhereNull('price_min');
        })->where(function($q) use ($price)
        {
            $q->where('price_max', '>=', $price)->orWhereNull('price_max');
        })->orderBy('price_min')->orderBy('price_max')->first();
    }

    public static function upPrice($price, $ShopUnloading)
    {
        $price = (int) $price;

        $ShopUnloadingUpPrice = self::getPriceOption($price, $ShopUnloading);

        if($ShopUnloadingUpPrice and ($price !== 0))
        {
            switch($ShopUnloadingUpPrice->up_price_type_id)
            {
                case 1: //₽
                    $price = $price + $ShopUnloadingUpPrice->up_price;
                    break;
                case 2: //%
                    $price = $price + $price*$ShopUnloadingUpPrice->up_price/100;
                    break;
            }
        }

        return (string) $price;
    }

    public static function boxSizesChange($ShopUnloading, &$Offer)
    {
        $sku = trim((string) $Offer->vendorCode);
        if($Product = Products::getProductBy('sku', $sku))
        {
            foreach($Offer->dimensions as $key => $Dimension)
            {
                $Offer->dimensions[$key] = "{$Product->BoxSizes->valueHeight}/{$Product->BoxSizes->valueWidth}/{$Product->BoxSizes->valueLength}";
            }
            //$Offer->dimensions = "{$Product->BoxSizes->valueHeight}/{$Product->BoxSizes->valueWidth}/{$Product->BoxSizes->valueLength}";
            $Offer->weight = $Product->BoxSizes->valueWeightKg;

            $ShopUnloadingsProductsOption = ShopUnloadingsProductsOption::where([
                ['product_id', $Product->id],
                ['shop_unloading_id', $ShopUnloading->id],
            ])->first();

            if($ShopUnloadingsProductsOption)
            {
                if(
                    $ShopUnloadingsProductsOption->new_box_height
                    or $ShopUnloadingsProductsOption->new_box_width
                    or $ShopUnloadingsProductsOption->new_box_length
                )
                {
                    if(isset($Offer->dimensions))
                    {
                        foreach($Offer->dimensions as $key => $Dimension)
                        {
                            if($dimensions = (string) $Dimension)
                            {
                                $dimensions = explode('/', $dimensions);

                                if($ShopUnloadingsProductsOption->new_box_height)
                                    $dimensions[0] = $ShopUnloadingsProductsOption->new_box_height;

                                if($ShopUnloadingsProductsOption->new_box_width)
                                    $dimensions[1] = $ShopUnloadingsProductsOption->new_box_width;

                                if($ShopUnloadingsProductsOption->new_box_length)
                                    $dimensions[2] = $ShopUnloadingsProductsOption->new_box_length;

                                $dimensions = implode('/', $dimensions);
                                //$Offer->dimensions = $dimensions;
                                $Offer->dimensions[$key] = $dimensions;
                            }
                            //$Offer->dimensions[$key] = "{$Product->BoxSizes->valueHeight}/{$Product->BoxSizes->valueWidth}/{$Product->BoxSizes->valueLength}";
                        }
                    }
                }

                if(isset($Offer->weight))
                {
                    if($ShopUnloadingsProductsOption->new_weight)
                        $Offer->weight = round($ShopUnloadingsProductsOption->new_weight / 1000, 3);
                }
            }
        }
    }

    public static function setPriceFromCRM(&$Offer)
    {
        $sku = trim((string) $Offer->vendorCode);
        if($Product = Products::getProductBy('sku', $sku))
        {
            $Offer->price = $Product->temp_price;
            if($Product->temp_old_price)
            {
                $Offer->oldprice = $Product->temp_old_price;
            }else
            {
                $Offer->oldprice = $Offer->price; // test if not deleted
                unset($Offer->oldprice);
            }
        }

    }

    public static function priceChange($ShopUnloading, &$Offer)
    {
        $Offer->price = self::upPrice($Offer->price, $ShopUnloading);
        if(isset($Offer->oldprice)) $Offer->oldprice = self::upPrice($Offer->oldprice, $ShopUnloading);

        $Offer->vat = 'NO_VAT';
    }

    public static function quantityChange($ShopUnloading, &$Offer)
    {
        if($ShopUnloading->shop_id)
        {
            $sku = trim((string) $Offer->vendorCode);

            $quantity = 0;
            if($Product = Products::getProductBy('sku', $sku))
            {
                $Stop = Products::getSystemsProductsStopResult($Product, $ShopUnloading->shop_id);
                if(!$Stop->stock)
                    $quantity = $Product->shopAmounts($ShopUnloading->shop_id)->amounts->balance??0;
            };

            if($quantity == 0)
            {
                $Offer['available'] = 'false';
            }else{
                $Offer->addChild('outlets');
                $Offer->outlets->addChild('outlet');
                $Offer->outlets->outlet->addAttribute('id', '0');
                $Offer->outlets->outlet->addAttribute('instock', $quantity);
                $Offer['available'] = 'true';
            }
        }

    }

    public static function clearQuantities(&$offers)
    {
        $count = count($offers);
        $j = 0;
        for ($i = 0; $i < $count; $i++)
        {
            if(((string) $offers[$j]['available']) === 'false')
            {
                unset($offers[$j]);
                $j = $j - 1;
            }
            $j = $j + 1;
        }
    }

    public static function clearArchives(&$offers)
    {
        $count = count($offers);
        $j = 0;
        for ($i = 0; $i < $count; $i++)
        {
            $sku = trim((string) $offers[$j]->vendorCode);
            if($Product = Products::getProductBy('sku', $sku))
            {
                if($Product->archive === 1)
                {
                    unset($offers[$j]);
                    $j = $j - 1;
                }
            }
            $j = $j + 1;
        }
    }

    public static function removeTest(&$offers)
    {
        $count = count($offers);
        $j = 0;
        for ($i = 0; $i < $count; $i++)
        {
            $sku = trim((string) $offers[$j]->vendorCode);
            if($sku == $skuTest)
            {
                unset($offers[$j]);
                $j = $j - 1;
            }
            $j = $j + 1;
        }
    }

    public static function removeMarkdown(&$offers)
    {
        $count = count($offers);
        $j = 0;
        for ($i = 0; $i < $count; $i++)
        {
            if(isset($offers[$j]->condition))
            {
                unset($offers[$j]);
                $j = $j - 1;
            }
            $j = $j + 1;
        }
    }

    public static function removeOther(&$offers, $shopId)
    {
        $count = count($offers);
        $j = 0;
        for ($i = 0; $i < $count; $i++)
        {
            $remove = false;
            $sku = trim((string) $offers[$j]->vendorCode);

            if($Product = Products::getProductBy('sku', $sku))
            {
                if(in_array($shopId, [67, 71, 72, 73, 174, 176])) // yandex
                {
                    if($Product->yandex_not_upload === 1)
                        $remove = true;
                }
            }else
            {
                $remove = true;
            }

            if($remove)
            {
                unset($offers[$j]);
                $j = $j - 1;
            }

            $j = $j + 1;
        }
    }



    public static function idChange(&$Offer, $attr = 'id')
    {
        // change ID to CRM id
        $sku = trim((string) $Offer->vendorCode);
        if($Product = Products::getProductBy('sku', $sku))
        {
            $Offer['id'] = $Product->{$attr}; // change ID to CRM-ID
        }
    }

    public static function addPeriodOfValidityDays($Offer)
    {
        $PeriodOfValidityDays = $Offer->addChild('period-of-validity-days', self::getPeriodOfValidityDays());
    }

    public static function addOtherAttributes(&$Offer)
    {
        $sku = trim((string) $Offer->vendorCode);
        if($Product = Products::getProductBy('sku', $sku))
        {
            $Offer->addChild('barcode', $Product->barcode);

            $height = $Product->box_height?:$Product->category->temp_height;
            $depth = $Product->box_width?:$Product->category->temp_depth;
            $width = $Product->box_length?:$Product->category->temp_width;

            $Offer->addChild('dimensions', "$height/$depth/$width");
        }
    }

    public static function getPeriodOfValidityDays($returnCountDays = false)
    {
        if($returnCountDays) return 1;
        return 'P1Y'; // from 2022-03-24 by Oleg
        //return 'P100Y'; // from 2022-01-07 by Irina

        $countDaysInMonth = 30;
        $countDaysInYear = 365;

        $oneYearDayFromStartWeek = Carbon::now()->startOfWeek()->addYear();
        $leftDays = $oneYearDayFromStartWeek->diffInDays(Carbon::now());

        if($returnCountDays) return $leftDays;

        $countDays = $leftDays % $countDaysInMonth;
        $countMonth = intdiv($leftDays, $countDaysInMonth);
        $countYears = intdiv($countMonth, 12);
        $countMonth -= $countYears * 12;

        $periodOfValidityDays = 'P';
        if($countYears > 0) $periodOfValidityDays .= "{$countYears}Y";
        if($countMonth > 0) $periodOfValidityDays .= "{$countMonth}M";
        if($countDays > 0) $periodOfValidityDays .= "{$countDays}D";

        return $periodOfValidityDays;
    }










    public static function getYmlProductName($Product, $unloadingId)
    {
        $ShopUnloadingsProductsOption = ShopUnloadingsProductsOption::where([
            ['product_id', $Product->id],
            ['shop_unloading_id', $unloadingId],
        ])->first();

        if($ShopUnloadingsProductsOption)
        {
            if($ShopUnloadingsProductsOption->new_title)
                return $ShopUnloadingsProductsOption->new_title;
        }else
        {
            return $Product->name_ru;
        }
    }
}


