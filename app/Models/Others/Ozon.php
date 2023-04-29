<?php

namespace App\Models\Others;

use App\Console\Api\OzonApi;
use App\Console\Api\OzonApi2;
use App\Console\Api\OzonFBOApi;
use App\Eloquent\Directories\Cost;
use App\Eloquent\Order\Order;
use App\Eloquent\Order\OrdersTypeShop;
use App\Eloquent\Other\Ozon\OzonClusterCoefficient;
use App\Eloquent\Other\Ozon\OzonClusterDelivery;
use App\Eloquent\Other\Ozon\OzonCommission;
use App\Eloquent\Other\Ozon\OzonPercentByProduct;
use App\Eloquent\Other\Ozon\OzonQuota;
use App\Eloquent\Other\OzonActionsAutoFilter;
use App\Eloquent\Other\OzonActionsAutoHistory;
use App\Eloquent\Other\OzonActionsOption;
use App\Eloquent\Other\OzonActionsProductsOption;
use App\Eloquent\Other\OzonMaxIndex;
use App\Eloquent\Products\Product;
use App\Eloquent\Products\ProductShopCategoriesAttribute;
use App\Eloquent\Products\TypeShopProduct;
use App\Eloquent\Sales\Sale;
use App\Eloquent\Sales\SalesFinancesFromAPI;
use App\Eloquent\Shops\Products\ShopProductsCategoriesAttribute;
use App\Eloquent\Shops\Products\ShopProductsCategoriesAttributesValue;
use App\Eloquent\System\SystemsOrdersStatus;
use App\Eloquent\System\SystemsProductsStop;
use App\Eloquent\Warehouse\WarehouseProductsAmount;
use App\Models\Directories\Costs;
use App\Models\Model;
use App\Models\Others\Yandex\YandexFBY;
use App\Models\Prices\Price;
use App\Models\Products;
use App\Models\Shops\Shops;
use Carbon\Carbon;

class Ozon extends Model{

    public static function getMaxIndex($shopId, $date = false): \stdClass
    {
        $res = new \stdClass();
        $res->displayDatedGoods = 1; // default all checks with date are removing
        $res->maxIndex = 2; // 2 equals max index

        if($date)
        {
            $Today = Carbon::parse($date, 'Europe/Moscow');
        }else{
            $Today = Carbon::now()->setTimezone('Europe/Moscow');
        }

        $date = $Today->toDateString();
        $time = $Today->toTimeString();

        $OzonMaxIndex = OzonMaxIndex::where([
            ['type_shop_id', $shopId],
            ['works', 1],
        ]);
        $OzonMaxIndex->where(function($q) use ($date)
        {
            $q->where('since_date', '<=', $date)
                ->orWhere('since_date', NULL);
        });
        $OzonMaxIndex->where(function($q) use ($date)
        {
            $q->where('to_date', '>=', $date)
                ->orWhere('to_date', NULL);
        });

        $OzonMaxIndex->where(function($q) use ($time)
        {
            $q->where('since_time', '<=', $time)
                ->orWhere('since_time', NULL);
        });
        $OzonMaxIndex->where(function($q) use ($time)
        {
            $q->where('to_time', '>=', $time)
                ->orWhere('to_time', NULL);
        });

        if($OzonMaxIndex = $OzonMaxIndex->first())
        {
            $res->maxIndex = $OzonMaxIndex->max_index;
            $res->displayDatedGoods = $OzonMaxIndex->display_dated_goods;
            $res->id = $OzonMaxIndex->id;
        }

        return $res;
    }

    public static function checkDifferentBetweenOzonAndApiIndexes($shopId)
    {
        $OzonApi = Ozon::getOzonApiByShopId($shopId);
        $stocksToImport = $OzonApi->updatePricesAndStocks('return_stocksToImport');
        $ozonIndexes = $OzonApi->getIndex(true)->products;

        foreach($stocksToImport as $StockToImport)
        {
            if($StockToImport->offer_id === '561699') dd($StockToImport);

            if(isset($StockToImport->price_index) and ($StockToImport->price_index > 0) and ($StockToImport->stock > 0)) // wrong =((
            {
                $found = false;
                foreach($ozonIndexes as $key => $OzonIndex)
                {
                    if($StockToImport->offer_id === $OzonIndex->offer_id)
                    {
                        $found = true;
                        unset($ozonIndexes[$key]);
                        break;
                    }
                }

                if(!$found)
                {
                    echo PHP_EOL."$StockToImport->offer_id $StockToImport->price_index";
                }
            }

        }

        dd($ozonIndexes);
    }

    public static function changeOrderTrackNumber($Order, $track)
    {
        $OzonApi = Ozon::getOzonApiByShopId($Order->type_shop_id);
        return $OzonApi->orderTrackingNumberSet($Order->system_order_id, $track);
    }

    public static function changeOrderStatus($Order, $systemsOrdersStatusId)
    {
        $OzonApi = Ozon::getOzonApiByShopId($Order->type_shop_id);
        $SystemOrderStatusAlias = SystemsOrdersStatus::where('id', $systemsOrdersStatusId)->firstOrFail()->alias;

        return $OzonApi->orderSetStatus($Order->system_order_id, $SystemOrderStatusAlias, $Order->info->status_reason);
    }


    public static function actionsUpdateProductsPrices($shopId, $log = false)
    {
        $OzonApi = self::getOzonApiByShopId($shopId);
        $actionsList = $OzonApi->getActionsList();
        $actionsListCount = count($actionsList);

        if($log) echo "Total $actionsListCount actions". PHP_EOL;

        if($log and !$actionsList) dd('Акций нет');

        foreach($actionsList as $actionKey => $Action)
        {
            if(!in_array($Action->action_type, ['DISCOUNT', 'STOCK_DISCOUNT'])) continue; // Only first lvl actions

            $today = Carbon::now()->setTimezone('Europe/Moscow')->startOfDay();
            $toDate = Carbon::now()->setTimezone('Europe/Moscow')->addDays(14)->endOfDay();

            $actionDateStart = Carbon::parse($Action->date_start)->setTimezone('Europe/Moscow');
            $actionDateEnd = Carbon::parse($Action->date_end)->setTimezone('Europe/Moscow');

            if(!in_array($Action->id, [166177, 166426]))
                if($actionDateStart > $toDate OR $actionDateEnd < $today) continue; // only today and +14 days update

            $actionProducts = $OzonApi->getActionsProducts($Action->id);

            if(!$actionProducts) continue;

            $actionProductsCount = count($actionProducts);
            $actionProductsNeedUpdate = [];
            foreach($actionProducts as $actionProductKey => $ActionProduct)
            {
                if($log)
                {
                    echo 'Checking products.. Action ' . ($actionKey + 1) . "/$actionsListCount. Action product " . ($actionProductKey + 1) . "/$actionProductsCount ";
                    if(($actionProductKey + 1) === $actionProductsCount)
                    {
                        echo PHP_EOL;
                    }else{
                        echo "\r";
                    }
                }


                $actionPrice = (float) $ActionProduct->action_price;
                $maxActionPrice = (float) $ActionProduct->max_action_price;

                //echo $actionPrice . ' ' . $maxActionPrice . PHP_EOL;

                if($actionPrice < $maxActionPrice)
                {
                    $UpdateActionProduct = new \stdClass();
                    $UpdateActionProduct->product_id = $ActionProduct->id;
                    $UpdateActionProduct->action_price = $maxActionPrice;
                    $actionProductsNeedUpdate[] = $UpdateActionProduct;
                }
            }

            if($actionProductsNeedUpdate)
            {
                if($OzonApi->deactivateActionProduct($Action->id, array_column($actionProductsNeedUpdate, 'product_id')))
                { // first exclude
                    if($productsIds = $OzonApi->activateActionProduct($Action->id, $actionProductsNeedUpdate))
                    { // next include
                        self::log('success', 'actionsUpdateProductsPrices', $actionProductsNeedUpdate, 'code 1');
                        if($log) echo 'Updated success (code 1): '. count($productsIds);
                    }else{
                        self::log('error', 'actionsUpdateProductsPrices', $actionProductsNeedUpdate, 'code 2');
                        if($log)
                        {
                            echo 'Update error (code 2): '. count($actionProductsNeedUpdate);
                            dd($actionProductsNeedUpdate);
                        }
                    }
                }else{
                    self::log('error', 'actionsUpdateProductsPrices', $actionProductsNeedUpdate, 'code 3');
                    if($log)
                    {
                        echo 'Update error (code 4): '. count($actionProductsNeedUpdate);
                        dd($actionProductsNeedUpdate);
                    }
                }
            }
        }
    }

    public static function addActionAutoHistory(
        $shopId,
        $product_id,
        $action_product_id,
        $action_id,
        $code,
        $type,
        $keyCode = false,
        $comment = false
    )
    {
        $OzonActionsAutoHistory = new OzonActionsAutoHistory;
        $OzonActionsAutoHistory->shop_id = $shopId;
        $OzonActionsAutoHistory->product_id = $product_id;
        $OzonActionsAutoHistory->action_product_id = $action_product_id;
        $OzonActionsAutoHistory->action_id = $action_id;
        $OzonActionsAutoHistory->code = $code;
        $OzonActionsAutoHistory->type = $type;
        if($keyCode) $OzonActionsAutoHistory->key_code = $keyCode;
        if($comment) $OzonActionsAutoHistory->comment = $comment;
        $OzonActionsAutoHistory->save();
    }

    public static function actionsUpdateProductsAutoOptions($shopId, $actionId = false, $log = false)
    {
        $start = microtime(true);

        $keyCode = uniqid();

        $OzonApi = self::getOzonApiByShopId($shopId);
        $actionsList = $OzonApi->getActionsList($actionId);
        $actionsListCount = count($actionsList);

        $auto1LvlInActive = OzonActionsOption::where([
            'alias' => 'auto-1lvl-in',
            'shop_id' => $shopId
        ])->firstOrFail()->active;

        $auto2LvlActionsActive = OzonActionsOption::where([
            'alias' => 'auto-2lvl-actions',
            'shop_id' => $shopId
        ])->firstOrFail()->active;

        $autoDismissProductsActionActive = OzonActionsOption::where([
            'alias' => 'auto-dismiss-products-action',
            'shop_id' => $shopId
        ])->firstOrFail()->active;

        $auto1LvlActionsActive = OzonActionsOption::where([
            'alias' => 'auto-1lvl-actions',
            'shop_id' => $shopId
        ])->firstOrFail()->active;

        $OzonActionsAutoFilter = OzonActionsAutoFilter::where('id', 1)->firstOrFail();

        if($log) echo "Total $actionsListCount actions". PHP_EOL;
        if($log and !$actionsList) dd('Акций нет');

        foreach($actionsList as $actionKey => $Action)
        {
            // NTH_FOR_FREE участвует в акции 1+1
            // SUM_CONDITION участвует в акции «скидка от суммы»;
            // DISCOUNT 1lvl

            $today = Carbon::now()->setTimezone('Europe/Moscow')->startOfDay();
            $toDate = Carbon::now()->setTimezone('Europe/Moscow')->addDays(14)->endOfDay();

            $actionDateStart = Carbon::parse($Action->date_start)->setTimezone('Europe/Moscow');
            $actionDateEnd = Carbon::parse($Action->date_end)->setTimezone('Europe/Moscow');

            if(!in_array($Action->id, [166177, 166426]))
                if($actionDateStart > $toDate OR $actionDateEnd < $today) continue; // only today and +14 days update

            $actionProductsCandidates = $OzonApi->getActionsCandidates($Action->id);
            $actionProductsCandidatesCount = count($actionProductsCandidates);

            $actionProductsRemove = []; // to remove from action
            $actionProductsAdd = []; // to add in action

            $actionProductsIds = array_column($actionProductsCandidates, 'id');
            $OzonApi->preloadAttributes($actionProductsIds);
            if($actionProductsCandidates) $OzonApi->arrayAddProductInfo($actionProductsCandidates);

            if($actionProductsCandidates)
            {
                foreach($actionProductsCandidates as $actionProductsCandidateKey => $ActionProductsCandidate)
                {
                    if($log)
                    {
                        echo 'Checking products.. Action ' . ($actionKey + 1) . "/$actionsListCount. Action product candidate " . ($actionProductsCandidateKey + 1) . "/$actionProductsCandidatesCount ";
                        if(($actionProductsCandidateKey + 1) === $actionProductsCandidatesCount) {
                            echo PHP_EOL;
                        } else {
                            echo "\r";
                        }
                    }

                    $Product = TypeShopProduct::where([
                            ['shop_product_id', $ActionProductsCandidate->id],
                            ['type_shop_id', $shopId]
                        ])->first()->product??false;
                    if(!$Product) continue;

                    $ActionProductsCandidate->in_action = false;

                    $addToAction = false;
                    $comment = '';
                    $addCode = 0;

                    if(in_array($Action->action_type, ['DISCOUNT', 'STOCK_DISCOUNT']))
                    {
                        // check add to lvl 1 auto-filter
                        if($auto1LvlInActive)
                        {
                            $AutoFilterRes = self::checkAutoFilter($Action, $ActionProductsCandidate, $shopId, $OzonActionsAutoFilter, $OzonApi, true);
                            if($AutoFilterRes->code > 0)
                            {
                                $addToAction = true;
                                $comment = $AutoFilterRes->comment;
                                $addCode = $AutoFilterRes->code;
                            }
                        }

                        // check add to lvl 1 all
                        if($auto1LvlActionsActive)
                        {
                            if(self::checkAuto1lvlIn($ActionProductsCandidate, $shopId, 'ozon_auto_discount'))
                            {
                                $addToAction = true;
                                $addCode = 5;
                            }
                        }
                    }else{
                        // check add to lvl 2
                        if($auto2LvlActionsActive and ($Action->action_type === 'NTH_FOR_FREE'))
                        {
                            if(self::checkAuto2lvlIn($ActionProductsCandidate, $shopId, 'ozon_auto_nth_for_free'))
                            {
                                $addToAction = true;
                                $addCode = 2;
                            }
                        }

                        // check add to lvl 2
                        if($auto2LvlActionsActive and ($Action->action_type === 'SUM_CONDITION'))
                        {
                            if(self::checkAuto2lvlIn($ActionProductsCandidate, $shopId, 'ozon_auto_sum_condition'))
                            {
                                $addToAction = true;
                                $addCode = 3;
                            }
                        }
                    }

                    if($autoDismissProductsActionActive and self::checkAutoDeactivate($ActionProductsCandidate, $shopId))
                        $addToAction = false;

                    switch(self::getOzonActionProductsOptionState($Product->id, $Action->id, $shopId))
                    {
                        case 1:
                            $addToAction = false;
                            break;
                        case 3:
                            if($addToAction !== true)
                            {
                                $addToAction = true;
                                $addCode = 4;
                            }
                            break;
                    }

                    if($addToAction)
                    {
                        $AddActionProduct = new \stdClass();
                        $AddActionProduct->product_id = $ActionProductsCandidate->id;
                        $AddActionProduct->action_price = (float) $ActionProductsCandidate->max_action_price;

                        // only for history
                        $AddActionProduct->history = new \stdClass();
                        $AddActionProduct->history->product_id = $Product->id;
                        $AddActionProduct->history->action_id = $Action->id;
                        $AddActionProduct->history->add_code = $addCode;
                        $AddActionProduct->history->comment = ($comment??'');

                        $actionProductsAdd[] = $AddActionProduct;
                    }
                }
            }

            $actionProducts = $OzonApi->getActionsProducts($Action->id);
            $actionProductsCount = count($actionProducts);

            $actionProductsIds = array_column($actionProducts, 'id');
            $OzonApi->preloadAttributes($actionProductsIds);
            if($actionProducts) $OzonApi->arrayAddProductInfo($actionProducts);

            if($actionProducts)
            {
                foreach($actionProducts as $actionProductKey => $ActionProduct)
                {
                    if($log)
                    {
                        echo 'Checking products.. Action ' . ($actionKey + 1) . "/$actionsListCount. Action product " . ($actionProductKey + 1) . "/$actionProductsCount ";
                        if(($actionProductKey + 1) === $actionProductsCount)
                        {
                            echo PHP_EOL;
                        }else{
                            echo "\r";
                        }
                    }

                    $Product = TypeShopProduct::where([
                            ['shop_product_id', $ActionProduct->id],
                            ['type_shop_id', $shopId]
                        ])->first()->product??false;
                    if(!$Product) continue;

                    $removeFromAction = false;
                    $comment = '';
                    $removeCode = 0;

                    if(in_array($Action->action_type, ['DISCOUNT', 'STOCK_DISCOUNT']))
                    {
                        // check add to lvl 1
                        if($auto1LvlInActive)
                        {
                            $AutoFilterRes = self::checkAutoFilter($Action, $ActionProduct, $shopId, $OzonActionsAutoFilter, $OzonApi, true);
                            if($AutoFilterRes->code < 0)
                            {
                                $removeFromAction = true;
                                $comment = $AutoFilterRes->comment??'';
                                $removeCode = $AutoFilterRes->code;
                            }
                        }

                        if($auto1LvlActionsActive)
                        {
                            if(self::checkAuto1lvlIn($ActionProduct, $shopId, 'ozon_auto_discount'))
                            {
                                $removeFromAction = false;
                            }
                        }
                    }

                    if($autoDismissProductsActionActive and self::checkAutoDeactivate($ActionProduct, $shopId))
                    {
                        $removeFromAction = true;
                        $removeCode = -1;
                    }

                    switch(self::getOzonActionProductsOptionState($Product->id, $Action->id, $shopId))
                    {
                        case 1:
                            if($removeFromAction !== true)
                            {
                                $removeFromAction = true;
                                $removeCode = -2;
                            }
                            break;
                        case 3:
                            $removeFromAction = false;
                            break;
                    }


                    if($removeFromAction)
                    {
                        $actionProductsRemove[] = $ActionProduct->id;

                        // there is remove code history
                        self::addActionAutoHistory(
                            $shopId,
                            $Product->id,
                            $ActionProduct->id,
                            $Action->id,
                            $removeCode, -1, $keyCode, ($comment??'')
                        );
                    }
                }
            }

            // clearing double products
            foreach($actionProductsAdd as $apaKey => $ActionProductAdd)
            {
                if(in_array($ActionProductAdd->product_id, $actionProductsRemove))
                {
                    unset($actionProductsAdd[$apaKey]);
                }else{
                    self::addActionAutoHistory(
                        $shopId,
                        $ActionProductAdd->history->product_id,
                        $ActionProductAdd->product_id,
                        $ActionProductAdd->history->action_id,
                        $ActionProductAdd->history->add_code,1, $keyCode,
                        $ActionProductAdd->history->comment
                    );
                    unset($ActionProductAdd->history);
                }
            }

            if($actionProductsAdd)
            {
                $OzonApi->activateActionProduct($Action->id, $actionProductsAdd);
                if($log) var_dump('$actionProductsAdd', $actionProductsAdd);
            }

            if($actionProductsRemove)
            {
                $OzonApi->deactivateActionProduct($Action->id, $actionProductsRemove);
                if($log) var_dump('$actionProductsRemove', $actionProductsRemove);
            }
        }

        $endText = 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек.';
        if($log) dump($endText);
        self::log('info', 'actionsUpdateProductsAutoOptions', $endText);
    }

    public static function checkAuto1lvlIn($ActionProduct, $shopId, $type)
    {
        $Product = TypeShopProduct::where([
                ['shop_product_id', $ActionProduct->id],
                ['type_shop_id', $shopId]
            ])->first()->product??false;
        if(!$Product) return false;

        $SystemsProductsStop = SystemsProductsStop::where([
            ['product_id', $Product->id],
            ['orders_type_shop_id', $shopId],
        ])->first();
        if(!$SystemsProductsStop) return false;

        return $SystemsProductsStop[$type];
    }

    public static function checkAuto2lvlIn($ActionProduct, $shopId, $type)
    {
        $Product = TypeShopProduct::where([
                ['shop_product_id', $ActionProduct->id],
                ['type_shop_id', $shopId]
            ])->first()->product??false;
        if(!$Product) return false;

        $SystemsProductsStop = SystemsProductsStop::where([
            ['product_id', $Product->id],
            ['orders_type_shop_id', $shopId],
        ])->first();
        if(!$SystemsProductsStop) return false;

        return $SystemsProductsStop[$type];
    }

    public static function checkAutoDeactivate($ActionProduct, $shopId)
    {
        $Product = TypeShopProduct::where([
                ['shop_product_id', $ActionProduct->id],
                ['type_shop_id', $shopId]
            ])->first()->product??false;
        if(!$Product) return false;

        $SystemsProductsStop = SystemsProductsStop::where([
            ['product_id', $Product->id],
            ['orders_type_shop_id', $shopId],
        ])->first();
        if(!$SystemsProductsStop) return false;

        if(!$SystemsProductsStop->ozon_auto_action_deactivate) return false;

        return true;
    }

    public static function getActionProductCalculations($shopId, $ActionProduct): \stdClass
    {
        $Calculations = new \stdClass();

        if(!isset($ActionProduct->product))
        {
            $ActionProduct->product = TypeShopProduct::where([
                ['shop_product_id', $ActionProduct->id],
                ['type_shop_id', $shopId]
            ])->first()->product??false;
        }

        $Calculations->commissionBeforeAction = Ozon::commission(
            $shopId,
            $ActionProduct->product
        );

        $Calculations->commissionAfterAction = Ozon::commission(
            $shopId,
            $ActionProduct->product,
            $ActionProduct->max_action_price
        );

        $Calculations->priceByUnloadingOption = $ActionProduct->product->priceByUnloadingOption($shopId);

        $CalculatedPrice = Ozon::deliveryPrice($ActionProduct->product, $Calculations->priceByUnloadingOption, $shopId);
        $Calculations->deliveryPrice = $CalculatedPrice->deliveryPrice;
        $Calculations->deliveryPriceText = $CalculatedPrice->deliveryPriceText;

        $CalculatedPriceMax = Ozon::deliveryPrice($ActionProduct->product, $ActionProduct->max_action_price, $shopId);
        $Calculations->deliveryPriceMax = $CalculatedPriceMax->deliveryPrice;
        $Calculations->deliveryPriceMaxText = $CalculatedPriceMax->deliveryPriceText;

        $Calculations->margin_before_action =
            $Calculations->priceByUnloadingOption
            - $Calculations->commissionBeforeAction->value
            - $Calculations->deliveryPrice
            - $ActionProduct->product->purchase_average_price;

        $Calculations->margin_before_action_text = "{$Calculations->priceByUnloadingOption} - {$Calculations->commissionBeforeAction->value} - {$Calculations->deliveryPrice} - {$ActionProduct->product->purchase_average_price}";

        $Calculations->margin_after_action =
            $ActionProduct->max_action_price
            - $Calculations->commissionAfterAction->value
            - $Calculations->deliveryPriceMax
            - $ActionProduct->product->purchase_average_price;

        $Calculations->margin_after_action_text = "$ActionProduct->max_action_price - {$Calculations->commissionAfterAction->value} - {$Calculations->deliveryPriceMax} - {$ActionProduct->product->purchase_average_price}";

        $Calculations->decrease =
            $Calculations->margin_after_action - $Calculations->margin_before_action;

        $Calculations->decrease_percent =
            $Calculations->margin_after_action
            / $Calculations->margin_before_action
            * 100
            - 100;

        $Calculations->evaluative =
            ($ActionProduct->product->purchase_average_price == 0)?0:$Calculations->margin_after_action
            / $ActionProduct->product->purchase_average_price;

        return $Calculations;
    }

    public static function checkAutoFilter(
        $Action,
        &$ActionProduct,
        $shopId,
        $OzonActionsAutoFilter = false,
        $OzonApi = false,
        $returnObject = false
    )
    {
        $return = new \stdClass();
        $return->code = 0;
        $return->comment = '';

        if(!in_array($Action->action_type, ['DISCOUNT', 'STOCK_DISCOUNT']))
        {
            $return->code = 0;
            return $returnObject?$return:$return->code;
        }

        $shopId = (int) $shopId;
        if(!$OzonActionsAutoFilter) $OzonActionsAutoFilter = OzonActionsAutoFilter::where('id', 1)->firstOrFail();

        if($OzonActionsAutoFilter->shop_id)
            if($OzonActionsAutoFilter->shop_id !== $shopId)
            {
                $return->code = 0;
                return $returnObject?$return:$return->code;
            }

        if($OzonActionsAutoFilter->action_ids)
        {
            if(($Action->id == $OzonActionsAutoFilter->action_ids)
                or (in_array($Action->id, explode(',', $OzonActionsAutoFilter->action_ids))))
            {
                // ok
            }else{
                $return->code = 0;
                return $returnObject?$return:$return->code;
            }
        }

        $actionDateStart = Carbon::parse($Action->date_start)->setTimezone('Europe/Moscow');
        $actionDateEnd = Carbon::parse($Action->date_end)->setTimezone('Europe/Moscow');

        if($OzonActionsAutoFilter->dateFrom)
            $fromDate = Carbon::parse($OzonActionsAutoFilter->dateFrom)->setTimezone('Europe/Moscow');

        if($OzonActionsAutoFilter->dateTo)
            $toDate = Carbon::parse($OzonActionsAutoFilter->dateTo)->setTimezone('Europe/Moscow');


        if(isset($fromDate) and isset($toDate))
        {
            if($actionDateStart > $toDate OR $actionDateEnd < $fromDate)
            {
                $return->code = 0;
                return $returnObject?$return:$return->code;
            }
        }else{
            if(isset($fromDate))
            {
                if($actionDateStart < $fromDate)
                {
                    $return->code = 0;
                    return $returnObject?$return:$return->code;
                }
            }else if(isset($toDate))
            {
                if($actionDateStart > $toDate)
                {
                    $return->code = 0;
                    return $returnObject?$return:$return->code;
                }
            }
        }

        switch($OzonActionsAutoFilter->in_action)
        {
            case 1: // all
                break;
            case 2: // in
                    if(!$ActionProduct->in_action)
                    {
                        $return->code = 0;
                        return $returnObject?$return:$return->code;
                    }
                break;
            case 3: // out
                    if($ActionProduct->in_action)
                    {
                        $return->code = 0;
                        return $returnObject?$return:$return->code;
                    }
                break;
        }

        $Product = TypeShopProduct::where([
            ['shop_product_id', $ActionProduct->id],
            ['type_shop_id', $shopId]
        ])->first()->product??false;
        if(!$Product)
        {
            $return->code = 0;
            return $returnObject?$return:$return->code;
        }

        if($OzonActionsAutoFilter->product_group_id and ($Product->group_id !== $OzonActionsAutoFilter->product_group_id))
        {
            $return->code = 0;
            return $returnObject?$return:$return->code;
        }

        $ActionProduct->product = $Product;

        $actionProductPseudoQuantity = Ozon::actionProductPseudoQuantity($Product, $shopId);
        if($actionProductPseudoQuantity < $OzonActionsAutoFilter->available)
        {
            $return->code = 0;
            return $returnObject?$return:$return->code;
        }


        if(!isset($ActionProduct->ozon)) $ActionProduct->ozon = new \stdClass();
        if(!isset($ActionProduct->ozon->stock)) $ActionProduct->ozon->stock = $OzonApi->getStockV3($Product->sku, false);
        if(!$ActionProduct->ozon->stock)
        {
            $return->code = 0;
            return $returnObject?$return:$return->code;
        }

        if(!isset($ActionProduct->calculations)) // can rewrite if set from controller?
            $ActionProduct->calculations = Ozon::getActionProductCalculations($shopId, $ActionProduct);

        if(!isset($ActionProduct->productSystemStop))
        {
            $ActionProduct->productSystemStop = $ActionProduct->product->systemsProductsStop($shopId);
        }
        $ActionProduct->ProductEvaluative = Ozon::getProductEvaluative($ActionProduct->productSystemStop);

        // check Individual Evaluate value
        if($ActionProduct->ProductEvaluative->value and $OzonActionsAutoFilter->plus_evaluative_from)
        {
            if($ActionProduct->ProductEvaluative->value < $ActionProduct->calculations->evaluative)
            {
                $return->code = 1021;
            }else
            {
                $return->code = -1021;
            }
            $return->comment = "
                Попадание по МОП: {$ActionProduct->ProductEvaluative->value}<br/>
                ID продукта = {$ActionProduct->id}<br/>
                SKU продукта = {$ActionProduct->product->sku}<br/>
                Цена продукта = {$ActionProduct->product->priceByUnloadingOption($shopId)}₽<br/>
                Закупка продукта = {$ActionProduct->product->purchase_average_price}₽<br/>
                Комиссия продукта = {$ActionProduct->calculations->commissionAfterAction->value}₽<br/>
                Стоимость доставки = {$ActionProduct->calculations->deliveryPrice}₽<br/>
                Максимальная цена по акции = {$ActionProduct->max_action_price}₽<br/>
                Маржа перед акцией = {$ActionProduct->calculations->margin_before_action}₽<br/>
                Маржа после акции = {$ActionProduct->calculations->margin_after_action}₽<br/>
                Уменьшение = {$ActionProduct->calculations->decrease}₽<br/>
                Оценочный = {$ActionProduct->calculations->evaluative}<br/>
                {$ActionProduct->ProductEvaluative->value} < {$ActionProduct->calculations->evaluative}
            ";
            return $returnObject?$return:$return->code;
        }

        // first filtering remove !!!
        if($OzonActionsAutoFilter->sub_decrease)
        {
            if($ActionProduct->calculations->decrease < -$OzonActionsAutoFilter->sub_decrease)
            {
                $return->code = -101;
                $return->comment = "
                    ID продукта = {$ActionProduct->id}<br/>
                    SKU продукта = {$ActionProduct->product->sku}<br/>
                    Цена продукта = {$ActionProduct->product->priceByUnloadingOption($shopId)}₽<br/>
                    Закупка продукта = {$ActionProduct->product->purchase_average_price}₽<br/>
                    Комиссия продукта = {$ActionProduct->calculations->commissionAfterAction->value}₽<br/>
                    Стоимость доставки = {$ActionProduct->calculations->deliveryPrice}₽<br/>
                    Максимальная цена по акции = {$ActionProduct->max_action_price}₽<br/>
                    Маржа перед акцией = {$ActionProduct->calculations->margin_before_action}₽<br/>
                    Маржа после акции = {$ActionProduct->calculations->margin_after_action}₽<br/>
                    Уменьшение = {$ActionProduct->calculations->decrease}₽<br/>
                    Оценочный = {$ActionProduct->calculations->evaluative}<br/>
                    {$ActionProduct->calculations->decrease} < -{$OzonActionsAutoFilter->sub_decrease}
                ";

                return $returnObject?$return:$return->code;
            }
        }

        // remove standard evaluate
        if($OzonActionsAutoFilter->plus_evaluative_from)
        {
            if($OzonActionsAutoFilter->plus_evaluative_from > $ActionProduct->calculations->evaluative)
            {
                $return->code = -102;
                $return->comment = "
                        ID продукта = {$ActionProduct->id}<br/>
                        SKU продукта = {$ActionProduct->product->sku}<br/>
                        Цена продукта = {$ActionProduct->product->priceByUnloadingOption($shopId)}₽<br/>
                        Закупка продукта = {$ActionProduct->product->purchase_average_price}₽<br/>
                        Комиссия продукта = {$ActionProduct->calculations->commissionAfterAction->value}₽<br/>
                        Стоимость доставки = {$ActionProduct->calculations->deliveryPrice}₽<br/>
                        Максимальная цена по акции = {$ActionProduct->max_action_price}₽<br/>
                        Маржа перед акцией = {$ActionProduct->calculations->margin_before_action}₽<br/>
                        Маржа после акции = {$ActionProduct->calculations->margin_after_action}₽<br/>
                        Уменьшение = {$ActionProduct->calculations->decrease}₽<br/>
                        Оценочный = {$ActionProduct->calculations->evaluative}<br/>
                        {$OzonActionsAutoFilter->plus_evaluative_from} > {$ActionProduct->calculations->evaluative}
                    ";
                return $returnObject?$return:$return->code;
            }
        }



        // second filtering add !!!
        if($OzonActionsAutoFilter->plus_decrease)
        {
            if($ActionProduct->calculations->decrease >= -$OzonActionsAutoFilter->plus_decrease)
            {
                $return->code = 101;
                $return->comment = "
                    {$ActionProduct->calculations->decrease} >= -{$OzonActionsAutoFilter->plus_decrease}<br/>
                    <br/>
                    ID продукта = {$ActionProduct->id}<br/>
                    SKU продукта = {$ActionProduct->product->sku}<br/>
                    Цена продукта = {$ActionProduct->product->priceByUnloadingOption($shopId)}₽<br/>
                    Закупка продукта = {$ActionProduct->product->purchase_average_price}₽<br/>
                    Комиссия продукта = {$ActionProduct->calculations->commissionAfterAction->value}₽<br/>
                    Стоимость доставки = {$ActionProduct->calculations->deliveryPrice}₽<br/>
                    Максимальная цена по акции = {$ActionProduct->max_action_price}₽<br/>
                    Маржа перед акцией = {$ActionProduct->calculations->margin_before_action}₽<br/>
                    Маржа после акции = {$ActionProduct->calculations->margin_after_action}₽<br/>
                    Уменьшение = {$ActionProduct->calculations->decrease}₽<br/>
                    Оценочный = {$ActionProduct->calculations->evaluative}<br/>
                    {$ActionProduct->calculations->decrease} < -{$OzonActionsAutoFilter->sub_decrease}
                ";

                return $returnObject?$return:$return->code;
            }
        }

        if($OzonActionsAutoFilter->plus_evaluative_from and $OzonActionsAutoFilter->plus_evaluative_to)
        {
            if(($OzonActionsAutoFilter->plus_evaluative_from < $ActionProduct->calculations->evaluative)
                and
            ($OzonActionsAutoFilter->evaluative_to > $ActionProduct->calculations->evaluative))
            {
                $return->code = 102;
                //$return->comment = $OzonActionsAutoFilter->plus_evaluative_from . ' < ' .$ActionProduct->calculations->evaluative . ' < ' . $OzonActionsAutoFilter->evaluative_to;
                $return->comment = "
                    {$OzonActionsAutoFilter->plus_evaluative_from} < {$ActionProduct->calculations->evaluative} < {$OzonActionsAutoFilter->evaluative_to}<br/>
                    <br/>
                    ID продукта = {$ActionProduct->id}<br/>
                    SKU продукта = {$ActionProduct->product->sku}<br/>
                    Цена продукта = {$ActionProduct->product->priceByUnloadingOption($shopId)}₽<br/>
                    Закупка продукта = {$ActionProduct->product->purchase_average_price}₽<br/>
                    Комиссия продукта = {$ActionProduct->calculations->commissionAfterAction->value}₽<br/>
                    Стоимость доставки = {$ActionProduct->calculations->deliveryPrice}₽<br/>
                    Максимальная цена по акции = {$ActionProduct->max_action_price}₽<br/>
                    Маржа перед акцией = {$ActionProduct->calculations->margin_before_action}₽<br/>
                    Маржа после акции = {$ActionProduct->calculations->margin_after_action}₽<br/>
                    Уменьшение = {$ActionProduct->calculations->decrease}₽<br/>
                    Оценочный = {$ActionProduct->calculations->evaluative}<br/>
                    {$ActionProduct->calculations->decrease} < -{$OzonActionsAutoFilter->sub_decrease}
                ";
                return $returnObject?$return:$return->code;
            }
        }else{
            if($OzonActionsAutoFilter->plus_evaluative_from)
            {
                if($OzonActionsAutoFilter->plus_evaluative_from < $ActionProduct->calculations->evaluative)
                {
                    $return->code = 102;
                    $return->comment = "
                        ID продукта = {$ActionProduct->id}<br/>
                        SKU продукта = {$ActionProduct->product->sku}<br/>
                        Цена продукта = {$ActionProduct->product->priceByUnloadingOption($shopId)}₽<br/>
                        Закупка продукта = {$ActionProduct->product->purchase_average_price}₽<br/>
                        Комиссия продукта = {$ActionProduct->calculations->commissionAfterAction->value}₽<br/>
                        Стоимость доставки = {$ActionProduct->calculations->deliveryPrice}₽<br/>
                        Максимальная цена по акции = {$ActionProduct->max_action_price}₽<br/>
                        Маржа перед акцией = {$ActionProduct->calculations->margin_before_action}₽<br/>
                        Маржа после акции = {$ActionProduct->calculations->margin_after_action}₽<br/>
                        Уменьшение = {$ActionProduct->calculations->decrease}₽<br/>
                        Оценочный = {$ActionProduct->calculations->evaluative}<br/>
                        {$OzonActionsAutoFilter->plus_evaluative_from} < -{$ActionProduct->calculations->evaluative} < -
                    ";
                    return $returnObject?$return:$return->code;
                }
            }else if($OzonActionsAutoFilter->plus_evaluative_to)
            {
                if($OzonActionsAutoFilter->plus_evaluative_to > $ActionProduct->calculations->evaluative)
                {
                    $return->code = 102;
                    //$return->comment =  ' - < ' . $ActionProduct->calculations->evaluative . ' < ' . $OzonActionsAutoFilter->plus_evaluative_to;
                    $return->comment = "
                        - < {$ActionProduct->calculations->evaluative} < {$OzonActionsAutoFilter->plus_evaluative_to}<br/>
                        <br/>
                        ID продукта = {$ActionProduct->id}<br/>
                        SKU продукта = {$ActionProduct->product->sku}<br/>
                        Цена продукта = {$ActionProduct->product->priceByUnloadingOption($shopId)}₽<br/>
                        Закупка продукта = {$ActionProduct->product->purchase_average_price}₽<br/>
                        Комиссия продукта = {$ActionProduct->calculations->commissionAfterAction->value}₽<br/>
                        Стоимость доставки = {$ActionProduct->calculations->deliveryPrice}₽<br/>
                        Максимальная цена по акции = {$ActionProduct->max_action_price}₽<br/>
                        Маржа перед акцией = {$ActionProduct->calculations->margin_before_action}₽<br/>
                        Маржа после акции = {$ActionProduct->calculations->margin_after_action}₽<br/>
                        Уменьшение = {$ActionProduct->calculations->decrease}₽<br/>
                        Оценочный = {$ActionProduct->calculations->evaluative}<br/>
                        {$OzonActionsAutoFilter->plus_evaluative_from} < -{$ActionProduct->calculations->evaluative} < -
                    ";
                    return $returnObject?$return:$return->code;
                }
            }
        }

        return $returnObject?$return:$return->code; // all right
    }

    public static function getOzonActionProductsOption($product_id, $action_id, $shopId)
    {
        return OzonActionsProductsOption::where([
            ['shop_id', $shopId],
            ['product_id', $product_id],
            ['action_id', $action_id],
        ])->first();
    }

    public static function getOzonActionProductsOptionState($product_id, $action_id, $shopId)
    {
        $ProductOption = Ozon::getOzonActionProductsOption($product_id, $action_id, $shopId);
        return $ProductOption?$ProductOption->state:2;
    }



    public static function checkAutoFilterUnitTest($shopId, $actionProductId, $actionId)
    {
        $OzonApi = Ozon::getOzonApiByShopId($shopId);
        $actionList = $OzonApi->getActionsList();
        foreach($actionList as $Action)
        {
            if($Action->id === $actionId)
            {
                $actionProductList = $OzonApi->getActionsProducts($actionId);
                $actionProductsIds = array_column($actionProductList, 'id');
                $OzonApi->preloadAttributes($actionProductsIds);
                if($actionProductList) $OzonApi->arrayAddProductInfo($actionProductList);

                foreach($actionProductList as $ActionProduct)
                {
                    if($ActionProduct->id === $actionProductId)
                    {
                        $ActionProduct->in_action = true;
                        //dd(Ozon::checkAutoFilter($Action, $ActionProduct, 1, false, $OzonApi));
                    }
                }
            }
        }
    }

    public static function getOzonActionHistoryCodeInfo($code)
    {
        switch ($code)
        {
            case 1: return 'Опция "Автоматическое добавление в акции 1-уровня". Авто-фильтр.';
            case 2: return 'Опция "автоматическое участие в акциях 2-уровня". Галочка "участвует в акции 1+1".';
            case 3: return 'Опция "автоматическое участие в акциях 2-уровня". Галочка "участвует в акции «скидка от суммы»".';
            case 4: return 'Светофор: зелёный свет.';
            case 5: return 'Опция "автоматическое участие в акциях 1-уровня". Галочка "Участие в акциях 1 уровня".';

            case -1: return 'Галочка "Автоматически ИСКЛЮЧИТЬ товары согласно параметров из Номенклатуры"';
            case -2: return 'Светофор: красный свет';

            // NEXT IS FOR AUTO-FILTER
            case -101: return 'Авто-фильтр: исключение п.1 (Уменьшение БОЛЕЕ)';
            case -1021: return 'Авто-фильтр: исключение п.2.1 (МОП)';

            case 101: return 'Авто-фильтр: добавление п.1 (Уменьшение НЕ более)';
            case 1021: return 'Авто-фильтр: добавление п.2.1. (МОП)';
            case 102: return 'Авто-фильтр: добавление п.2 (Оценочный параметр)';
            case -102: return 'Авто-фильтр: исключение п.2 (Оценочный параметр)';

        }
        return 'Неизвестный код';
    }

    public static function removeActionsProductsOptionsWhereQuantityZero($shopId)
    {
        $comment = new \stdClass();
        $comment->shopId = $shopId;
        $comment->count = 0;
        $comment->skus = [];

        $ozonActionsProductsOptions = OzonActionsProductsOption::where('shop_id', $shopId)->get();
        foreach($ozonActionsProductsOptions as $OzonActionsProductsOption)
        {
            $actionProductPseudoQuantity = Ozon::actionProductPseudoQuantity($OzonActionsProductsOption->product, $shopId);

            if($actionProductPseudoQuantity === 0)
            {
                $comment->count++;
                $comment->skus[] = $OzonActionsProductsOption->product->sku??'Unknown sku';
                $OzonActionsProductsOption->delete();
            }
        }

        self::log('info', 'removeActionsProductsOptionsWhereQuantityZero', $comment);
    }

    // /TEST

    public static function getOzonApiByShopId($shopId)
    {
        if($Shop = OrdersTypeShop::where('id', $shopId)->first())
        {
            $shopId = $Shop->parent_shop_id?:$shopId;
        }

        $OzonApi = false;
        switch($shopId)
        {
            case 1:
                    $OzonApi = new OzonApi('Stavropol');
                break;
            case 2:
                    $OzonApi = new OzonApi('Moscow');
                break;
            case 74:
                    $OzonApi = new OzonFBOApi('Stavropol');
                break;
            case 10001:
                    $OzonApi = new OzonApi2('Stavropol');
                break;
            case 10002:
                $OzonApi = new OzonApi2('Moscow');
                break;
        }

        return $OzonApi;
    }


    public static function getAllProducts($shopId)
    {

        $OzonApi = self::getOzonApiByShopId($shopId);
        $productsAll = $OzonApi->getProductsListV2(['visibility' => 'ALL'], true);
        dump('1 '.count($productsAll));
        $productsArchived = $OzonApi->getProductsListV2(['visibility' => 'ARCHIVED'], true);
        dump('2 '.count($productsArchived));

        $productsFailed = $OzonApi->getProductsListV2(['visibility' => 'VALIDATION_STATE_FAIL'], true);
        dump('3 '.count($productsFailed));

        return array_merge($productsAll, $productsArchived, $productsFailed);
    }


    public static function updateProductsInfoFromOzon($shopId)
    {
        $OzonApi = self::getOzonApiByShopId($shopId);

        $allOzonProducts = Ozon::getAllProducts($shopId);

        $arrayIds = [];
        foreach($allOzonProducts as $OzonProduct)
        {
            $arrayIds[] = $OzonProduct->offer_id;
        }
        unset($allOzonProducts);

        $arrayOfIds = array_chunk($arrayIds, 100);

        //$arrayOfIds = [['DHL82']];

        foreach($arrayOfIds as $arrayOf100)
        {
            $productAttributes = $OzonApi->getAttributesV3(['offer_id' => $arrayOf100], false);

            foreach($productAttributes as $ProductAttribute)
            {
                if($Product = Product::where('sku', trim($ProductAttribute->offer_id))->first())
                {
                    if($ProductAttribute->depth) $Product->box_length = $ProductAttribute->depth/10;
                    if($ProductAttribute->width) $Product->box_width = $ProductAttribute->width/10;
                    if($ProductAttribute->height) $Product->box_height = $ProductAttribute->height/10;
                    if($ProductAttribute->weight) $Product->weight = $ProductAttribute->weight;
                    $Product->save();

                    /*

                    Products::setProductShopCategory($shopId, $Product->id, 0, $ProductAttribute->category_id);

                    foreach($ProductAttribute->attributes as $Attribute)
                    {
                        if(!isset($Attribute->values) or empty($Attribute->values)) continue;

                        if($ShopProductsCategoriesAttribute = ShopProductsCategoriesAttribute::where([
                            'shop_id' => $shopId,
                            'shop_attribute_id' => $Attribute->attribute_id,
                        ])->first())
                        {
                            $valueId = false;
                            if($Attribute->values[0]->dictionary_value_id)
                            {
                                if($ShopProductsCategoriesAttributesValue = ShopProductsCategoriesAttributesValue::where([
                                    'shop_id' =>  $shopId,
                                    'shop_products_categories_attribute_id' =>  $ShopProductsCategoriesAttribute->id,
                                    'shop_value_id' =>  $Attribute->values[0]->dictionary_value_id,
                                ])->first())
                                {
                                    $valueId = $ShopProductsCategoriesAttributesValue->id;
                                }
                            }

                            $ProductShopCategoriesAttribute = ProductShopCategoriesAttribute::firstOrNew([
                                'shop_id' => $shopId,
                                'product_id' => $Product->id,
                                'shop_products_categories_attribute_id' => $ShopProductsCategoriesAttribute->id,
                            ]);

                            if($valueId)
                            {
                                $ProductShopCategoriesAttribute->shop_products_categories_attributes_value_id = $valueId;
                                $ProductShopCategoriesAttribute->value = NULL;
                            }else
                            {
                                $ProductShopCategoriesAttribute->value = $Attribute->values[0]->value;
                                $ProductShopCategoriesAttribute->shop_products_categories_attributes_value_id = NULL;
                            }
                            $ProductShopCategoriesAttribute->save();
                        }
                    }
                    */
                }
            }
        }
    }

    public static function getNotAddedProducts($shopId)
    {
        $allOzonProducts = self::getAllProducts($shopId);

        $arrayIds = [];
        foreach($allOzonProducts as $OzonProduct)
        {
            $arrayIds[] = $OzonProduct->offer_id;
        }
        unset($allOzonProducts);

        $notAddedProducts = Product::where([
            ['state', '>', '-1'],
            ['temp_price', '>', 0],
            ['archive', 0],
            // ['markdown', 0], loading markdown
        ])->whereHas('images')->whereNotIn('sku', $arrayIds)->whereDoesntHave('typeShopProducts', function ($q) use ($shopId)
        {
            $q->where('type_shop_id', $shopId);
        })->get();

        /*
        $resArray = [];
        foreach($notAddedProducts as $NotAddedProduct)
        {
            $Stop = Products::getSystemsProductsStopResult($NotAddedProduct, $shopId);
            if(!$Stop->stock) $resArray[] = $NotAddedProduct;
        }

        return $resArray;
        */
        return $notAddedProducts;
    }

    public static function getOzonWarehousesByShopId($shopId): array
    {
        $ozonWarehouses = [];

        switch($shopId)
        {
            case 1:
                $ozonWarehouses = [
                    '17331638847000', //Южный склад
                    '22420023221000', //Южный склад (Ozon Express)
                    '23705943260000', //Южный склад (rFBS собственная логистика) from 2022-06-28
                ];
                break;
            case 2:
                $ozonWarehouses = [
                    '15682964066000', //Ritm-z (ООО «Профит поинт»)
                    '21202719649000', //Центральный склад (собственная логистика)
                ];
                break;
            case 10001:
                $ozonWarehouses = [
                    '22318950779000', //Южный склад №2
                ];
                break;
            case 10002:
                $ozonWarehouses = [
                    '23851995857000', //Южный склад №2
                ];
                break;
        }

        return $ozonWarehouses;
    }


    public static function getProductEvaluative(
        $SystemProductStop
    )
    {
        $Evaluative = new \stdClass();
        $Evaluative->value = 0;
        $Evaluative->info = 'Нет расчёта';

        if($SystemProductStop and $SystemProductStop->ozon_auto_action_max_discount and $SystemProductStop->ozon_auto_action_max_discount_value_type_id)
        {
            $shopId = $SystemProductStop->orders_type_shop_id?:1; // default STV
            $Product = $SystemProductStop->product;

            $price = (int) $Product->priceByUnloadingOption($shopId);

            switch($SystemProductStop->ozon_auto_action_max_discount_value_type_id)
            {
                case 1:
                    $priceDiscounted = $price - $SystemProductStop->ozon_auto_action_max_discount;
                    break;
                default:
                    $priceDiscounted = $price * (1 - $SystemProductStop->ozon_auto_action_max_discount / 100);
            }

            $ozonCommission = Ozon::commission($shopId, $Product, $priceDiscounted)->value;
            $CalculatedPriceMax = Ozon::deliveryPrice($Product, $priceDiscounted, $shopId);

            $marginAfterDiscount =
                $priceDiscounted
                - $ozonCommission
                - $CalculatedPriceMax->deliveryPrice
                - $Product->purchase_average_price;

            if($Product->purchase_average_price != 0)
                $Evaluative->value = $marginAfterDiscount / $Product->purchase_average_price;

            $marginAfterDiscount = round($marginAfterDiscount, 2);
            $Evaluative->value = round($Evaluative->value, 2);

            $Evaluative->info = "Доставка: $CalculatedPriceMax->deliveryPriceText = $CalculatedPriceMax->deliveryPrice".PHP_EOL."Маржа после скидки: цена после скидки:{$priceDiscounted}₽ - комиссия:{$ozonCommission}₽ - доставка:{$CalculatedPriceMax->deliveryPrice}₽ - закупка:{$Product->purchase_average_price}₽ = {$marginAfterDiscount}₽".PHP_EOL."Минимальный оценочный параметр: Маржа после скидки:{$marginAfterDiscount}₽ / {$Product->purchase_average_price}₽ = $Evaluative->value";
        }

        return $Evaluative;
    }

    public static function deliveryPrice(Product $Product, $productPrice, $shopId): \stdClass
    {
        $productPrice = (float) $productPrice;

        $DeliveryPrice = Ozon::actionDeliveryPrice($Product, $shopId, $productPrice);

        $res = new \stdClass();
        $res->deliveryPrice = round($DeliveryPrice->value, 2);
        $res->deliveryPriceText = $DeliveryPrice->formula;
        return $res;
    }

    public static function getOzonCommissions($shopId, $productId)
    {
        $Commissions = new \stdClass();
        $Commissions->rfbs = (object)
        [
            'percent' => 0,
        ];
        $Commissions->fbs = (object)
        [
            'percent' => 0,
        ];
        $Commissions->fbo = (object)
        [
            'percent' => 0,
        ];

        $ozonCommissions = OzonCommission::where([
            'shop_id' => $shopId,
            'product_id' => $productId,
        ])->get();

        foreach($ozonCommissions as $OzonCommission)
        {
            if(!isset($Commissions->{$OzonCommission->sale_schema})) $Commissions->{$OzonCommission->sale_schema} = new \stdClass();
            $Commissions->{$OzonCommission->sale_schema}->percent = $OzonCommission->percent;
        }

        return $Commissions;
    }

    public static function commission($shopId, Product $Product, $productPrice = false): \stdClass
    {
        $price = $productPrice?:$Product->priceByUnloadingOption($shopId);

        $Commission = new \stdClass();
        $Commission->percent = self::getOzonCommissions(1, $Product->id)->fbs->percent;
        $Commission->value = round($price * $Commission->percent / 100, 2);
        $Commission->info = "Комиссия: $price * $Commission->percent / 100";
        return $Commission;
    }

    public static function saveProductsCommissions($shopId)
    {
        $OzonApi = Ozon::getOzonApiByShopId($shopId);
        $ozonPrices = $OzonApi->getPricesV4();

        $total = count($ozonPrices);

        foreach($ozonPrices as $key => $OzonPrice)
        {
            dump("$key / $total");

            if(
                isset($OzonPrice->commissions->sales_percent)
                    and
                $Product = Product::where('sku', $OzonPrice->offer_id)->first()
            )
            {
                foreach(['rfbs', 'fbs', 'fbo'] as $saleSchema)
                {
                    $OzonCommission = OzonCommission::firstOrNew([
                        'shop_id' => $shopId,
                        'product_id' => $Product->id,
                        'sale_schema' => $saleSchema
                    ]);
                    $OzonCommission->percent = $OzonPrice->commissions->sales_percent;
                    $OzonCommission->save();
                }
            }
        }
    }


    public static function pseudoRemoveProducts($shopId, $products = false)
    {
        $OzonApi = Ozon::getOzonApiByShopId($shopId);
        $productsSku = [];

        if($products)
        {
            $productsSku = $products->pluck('sku')->toArray();
        }else
        {
            $ozonProducts = $OzonApi->getProductsListV2();
            foreach($ozonProducts as $OzonProduct)
            {
                $productsSku[] = $OzonProduct->offer_id;
            }
            unset($ozonProducts);
        }

        if(!$productsSku)
        {
            dump("Hasn't products sku");
            return false;
        }

        $req = ['offer_id' => $productsSku];

        $ozonTemplates = $OzonApi->getAttributesV3($req, false);


        // Fits - archive!
        $ozonProductsIds = [];
        foreach($ozonTemplates as $key => $OzonTemplate)
        {
            $ozonProductsIds[] = $OzonTemplate->id;
        }

        if($ozonProductsIds)
            $OzonApi->productsArchiveV1($ozonProductsIds);

        // Then change sku
        foreach($ozonTemplates as $key => $OzonTemplate)
        {
            $Product = Product::where('sku', $OzonTemplate->offer_id)->first();
            if(!$Product)
            {
                unset($ozonTemplates[$key]);
                continue;
            }

            $images = [];
            foreach($OzonTemplate->images as $iKey => $OzonTemplateImage)
            {
                if($iKey === 0) $OzonTemplate->primary_image = $OzonTemplateImage->file_name;
                $images[] = $OzonTemplateImage->file_name;
            }
            $OzonTemplate->images = $images;

            unset($OzonTemplate->id);

            // clear attributes
            foreach($OzonTemplate->attributes as $key => $Attribute)
            {
                $Attribute->id = $Attribute->attribute_id;
                unset($Attribute->attribute_id);

                // change sku for remove
                if($Attribute->id === 9024)
                {
                    $Attribute->values[0]->value = "$Product->sku-removed-".Carbon::now()->setTimezone('Europe/Moscow')->format('Y-m-d_H-i');
                    //$Attribute->values[0]->value = "$Product->sku-removed";
                    unset($Attribute->complex_id);
                    unset($Attribute->values[0]->dictionary_value_id);
                }
            }

            $OzonTemplate->attributes = array_values($OzonTemplate->attributes);

            $OzonTemplate->vat = $OzonApi->importVat;
            if(!$OzonTemplate->barcode) unset($OzonTemplate->barcode);
            $OzonTemplate->price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $shopId);
            if($Product->temp_old_price !== 0)
                $OzonTemplate->old_price = Price::recalculatePriceByUnloadingOption($Product->temp_old_price, $shopId);
            if($premiumPrice = $OzonApi->getPremiumPrice($OzonTemplate->price))
                $OzonTemplate->premium_price = $premiumPrice;
        }

        // send ready items
        $chunkedItems = array_chunk($ozonTemplates, 100);


        foreach($chunkedItems as $items)
        {
            $req = ['items' => $items];
            $res = $OzonApi->makeRequest(
                'POST',
                "/v2/product/import",
                $req
            );

            dump($res);
        }

        return true;
    }

    public static function updateProductImages($shopId, $products = false)
    {
        $OzonApi = self::getOzonApiByShopId($shopId);

        if(!$products)
            $products = Products::getRecentlyUpdatedProduct($shopId);

        $countProducts = count($products);
        $updated = 0;
        foreach($products as $key => $Product)
        {
            if($shopProductId = $Product->shopProductId($OzonApi->shopId))
            {
                if($images = Products::getShopImages($Product, $shopId))
                {
                    $imagesUrls = [];
                    foreach($images as $Image)
                    {
                        $imagesUrls[] = $Image->url;
                    }
                    $imagesUrls = array_slice($imagesUrls, 0, 15); // max 15 images

                    if($OzonApi->productPicturesImportV1($shopProductId, $imagesUrls))
                    {
                        $updated++;
                    }
                }
            }

            dump("$key / $countProducts $Product->sku");
        }

        dump("Updated $updated");
    }

    public static function productAttributesHasModel(&$OzonTemplate, $Product, $shopId, $change = false): bool
    {
        $found = false;

        $NewAttribute = (object) [
            'attribute_id' => 9048,
            'values' => [
                [
                    'value' => "oz-$shopId-$Product->id"
                ]
            ]
        ];

        foreach($OzonTemplate->attributes as $key => $Attribute)
        {
            if($Attribute->attribute_id === 9048)
            {
                if(isset($Attribute->values[0]->value) and $Attribute->values[0]->value)
                {
                    if($change) unset($OzonTemplate->attributes[$key]);
                    $found = true;
                    break;
                }
            }
        }

        $OzonTemplate->attributes = array_values($OzonTemplate->attributes);

        if($change or !$found)
        {
            $OzonTemplate->attributes[] = $NewAttribute;
            return false;
        }

        return true;
    }

    public static function updateProductInfo($shopId, $products = false, $change = false)
    {
        $OzonApi = Ozon::getOzonApiByShopId($shopId);
        $productsSku = [];

        if($products)
        {
            $productsSku = $products->pluck('sku')->toArray();
        }else
        {
            $ozonProducts = $OzonApi->getProductsListV2();
            foreach($ozonProducts as $OzonProduct)
            {
                $productsSku[] = $OzonProduct->offer_id;
            }
            unset($ozonProducts);
        }

        if(!$productsSku)
        {
            dump("Hasn't products sku");
            return false;
        }

        $req = ['offer_id' => $productsSku];
        $ozonTemplates = $OzonApi->getAttributesV3($req, false);
        $totalToCheck = count($ozonTemplates);

        dump(count($productsSku) . ' / ' . $totalToCheck);

        foreach($ozonTemplates as $key => $OzonTemplate)
        {
            dump("$key / $totalToCheck");

            $Product = Product::where('sku', $OzonTemplate->offer_id)->first();
            if(!$Product)
            {
                unset($ozonTemplates[$key]);
                continue;
            }

            $valueHeightMm = (int) $Product->BoxSizes->valueHeight * 10;
            $valueWidthMm = (int) $Product->BoxSizes->valueWidth * 10;
            $valueLengthMm = (int) $Product->BoxSizes->valueLength * 10;
            $valueWeightG = (int) $Product->BoxSizes->valueWeight; // set by Oleg 2022.04.08

            if(
                $OzonTemplate->height == $valueHeightMm
                and $OzonTemplate->width == $valueWidthMm
                and $OzonTemplate->depth == $valueLengthMm
                and $OzonTemplate->weight == $valueWeightG
                and self::productAttributesHasModel($OzonTemplate, $Product, $OzonApi->shopId, $change)
            )
            {
                unset($ozonTemplates[$key]);
                continue;
            }

            $OzonTemplate->dimension_unit = 'mm';
            $OzonTemplate->height = $valueHeightMm;
            $OzonTemplate->width = $valueWidthMm;
            $OzonTemplate->depth = $valueLengthMm;
            $OzonTemplate->weight_unit = 'g';
            $OzonTemplate->weight = $valueWeightG;

            $images = [];
            foreach($OzonTemplate->images as $iKey => $OzonTemplateImage)
            {
                if($iKey === 0) $OzonTemplate->primary_image = $OzonTemplateImage->file_name;
                $images[] = $OzonTemplateImage->file_name;
            }
            $OzonTemplate->images = $images;

            unset($OzonTemplate->id);

            // clear attributes
            foreach($OzonTemplate->attributes as $Attribute)
            {
                $Attribute->id = $Attribute->attribute_id;
                unset($Attribute->attribute_id);
            }

            $OzonTemplate->vat = $OzonApi->importVat;

            if(!$OzonTemplate->barcode) unset($OzonTemplate->barcode);
            $OzonTemplate->price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $shopId);
            if($Product->temp_old_price !== 0)
                $OzonTemplate->old_price = Price::recalculatePriceByUnloadingOption($Product->temp_old_price, $shopId);
            if($premiumPrice = $OzonApi->getPremiumPrice($OzonTemplate->price))
                $OzonTemplate->premium_price = $premiumPrice;
        }

        // send ready items
        dump('To update '. count($ozonTemplates));
        $chunkedItems = array_chunk($ozonTemplates, 100);

        foreach($chunkedItems as $items)
        {
            $req = ['items' => $items];

            //print_r(json_encode($req));
            $res = $OzonApi->makeRequest(
                'POST',
                "/v2/product/import",
                $req
            );

            //print_r(json_encode($res));
            dump($res);
        }

        dump('end');

        return true;
    }

    public static function getOzonTransactionServiceIdByAlias($type)
    {
        switch($type)
        {
            case 'MarketplaceServiceItemFulfillment': return 31; // услуга сборки заказов_Marketplace

            case 'MarketplaceServiceItemDirectFlowTrans': return 33; // Магистраль
            case 'MarketplaceServiceItemReturnFlowTrans': return 35; // обратная магистраль
            case 'MarketplaceServiceItemDropoffSC': return 32; // Обработка отправления drop-off на сортировочном центре
            case 'MarketplaceServiceItemReturnNotDelivToCustomer': return 36; // Обработка отмененных и невостребованных товаров


            case 'MarketplaceServiceItemReturnAfterDelivToCustomer': return 15;

            case 'MarketplaceServiceItemReturnPartGoodsCustomer': return 37; // ??




            case 'MarketplaceServiceItemDelivToCustomer': return 34; // re set twice

            default: dump("Unknown type: $type");

            // OperationReturnGoodsFBSofRMS(FBS) - Доставка и обработка возврата, отмены, невыкупа
        }
    }

    public static function updateTransactions($Sale)
    {
        if(isset($Sale->order->system_order_id))
        {
            SalesFinancesFromAPI::where('sale_id', $Sale->id)->delete();
            YandexFBY::saveOzonAPIFinances($Sale->type_shop_id, false, false, $Sale->order->system_order_id);
        }

    }


    public static function getOzonMinPrice($offerId, $OzonApi, $price = false)
    {
        $minPrice = false;
        //if(!$OzonApi) $OzonApi = self::getOzonApiByShopId($shopId);

        if(!$price)
        {
            $OzonPrice = $OzonApi->getPriceV4($offerId);

            if(
                isset($OzonPrice->price->marketing_price)
                and $marketingPrice = (float) $OzonPrice->price->marketing_price
            )
            {
                $price = $marketingPrice;
            }
        }else
        {
            $price = (float) $price;
        }

        if($price)
        {
            $minPrice = round($price * 0.9495); // - 5.05%
        }

        return $minPrice;
    }

    public static function getOzonPriceWithPercentByProduct($TotalPrice, $OzonPercentByProduct) // only for Ozon FBO
    {
        $priceWithOzonPercent = $TotalPrice * $OzonPercentByProduct->percent / 100;

        if($OzonPercentByProduct->min > $priceWithOzonPercent)
            $priceWithOzonPercent = $OzonPercentByProduct->min;

        if($OzonPercentByProduct->max < $priceWithOzonPercent)
            $priceWithOzonPercent = $OzonPercentByProduct->max;

        return $priceWithOzonPercent;
    }

    public static function getOzonTypeByShopId($shopId)
    {
        $shopId = Shops::getShopIdRules($shopId);
        switch ($shopId)
        {
            case 1:
            case 2:
            case 10001:
            case 10002: return 'FBS';

            case 74: return 'FBO';
        }

        return false;
    }

    public static function getPercentByProduct($SaleProduct) // only for Ozon FBO
    {
        if($OzonPercentByProduct =
            OzonPercentByProduct
                ::where([
                    ['ozon_type', self::getOzonTypeByShopId($SaleProduct->sale->type_shop_id)],
                    ['from_weight', '<=', $SaleProduct->product->BoxSizes->estimatedWeight]
                ])->orderBy('from_weight', 'DESC')->first())
        {
            return $OzonPercentByProduct;
        }

        return false;
    }

    public static function getPercentBySale($saleId) // only for Ozon FBO
    {
        if($Sale = Sale::where('id', $saleId)->first())
        {
            if($OzonPercentByProduct =
                OzonPercentByProduct
                    ::where([
                        ['ozon_type', self::getOzonTypeByShopId($Sale->type_shop_id)],
                        ['from_weight', '<=', $Sale->TotalEstimatedWeight]
                    ])->orderBy('from_weight', 'DESC')->first())
            {
                return $OzonPercentByProduct;
            }
        }

        return false;
    }

    public static function getClusterCoefficient($saleId, $useApi = true) // only for Ozon FBO
    {
        $coefficient = 1.5; // is default
        if($useApi and $Sale = Sale::where('id', $saleId)->first() and $Order = $Sale->order)
        {
            if($OzonApi = self::getOzonApiByShopId($Order->type_shop_id))
            {
                $OzonOrder = $OzonApi->getOrder($Order->system_order_id);

                if(
                    $OzonOrder
                    and isset($OzonOrder->analytics_data->region)
                    and isset($OzonOrder->analytics_data->city)
                    and isset($OzonOrder->analytics_data->warehouse_id)
                    and ($OzonClusterDelivery = OzonClusterCoefficient
                        ::whereHas('clusterDelivery', function($q) use ($OzonOrder)
                        {
                            $q->whereRaw('LOWER(name) LIKE "%'.$OzonOrder->analytics_data->region.'%"');
                            $q->orWhereRaw('LOWER(name) LIKE "%'.$OzonOrder->analytics_data->city.'%"');
                        })
                        ->whereHas('clusterShippingWarehouse', function($q) use ($OzonOrder)
                        {
                            $q->whereRaw('warehouse_ids LIKE "%'.$OzonOrder->analytics_data->warehouse_id.'%"');
                        })->first())
                )
                {
                    $coefficient = $OzonClusterDelivery->coefficient;
                }
            }
        }

        return $coefficient;
    }

    public static function updateStocks($products, $shopId)
    {
        $OzonApi = Ozon::getOzonApiByShopId($shopId);
        $stocksToImport = [];
        foreach($products as $Product)
        {
            $stock = new \stdClass();
            $stock->offer_id = $Product->sku;
            $stock->stock = 0;
            $stocksToImport[] = $stock;
        }

        $OzonApi->updateStocks($stocksToImport);
    }

    public static function actionDeliveryPrice($Product, $shopId, $price)
    {
        $Delivery = new \stdClass();
        $Delivery->value = 0;
        $Delivery->formula = '';

        $costIds = Cost::where('ozon_delivery', 1)->pluck('id')->toArray();

        $Sale = new \stdClass();
        $Sale->date_sale = Carbon::now();
        $Sale->type_shop_id = $shopId;
        $Sale->system_id = 3; // only for Ozon
        $Sale->packs = collect();
        $Sale->products = collect();

        if($costsDefaults = Costs::getCostsForSale(
            $Sale,
            false,
            false,
            [],
            $costIds
        ))
        {
            $costsDefaults = $costsDefaults->sort(function($a, $b)
            {
                return ($a->cost->ozon_delivery_order > $b->cost->ozon_delivery_order);
            });

            $OzonPercent1 = 0;
            $priceWithOzonPercent = $price;
            $cluster = 1.5;

            if($OzonPercentByProduct =
                OzonPercentByProduct
                    ::where([
                        ['ozon_type', self::getOzonTypeByShopId($shopId)],
                        ['from_weight', '<=', $Product->BoxSizes->estimatedWeight]
                    ])->orderBy('from_weight', 'DESC')->first())
            {
                $OzonPercent1 = $OzonPercentByProduct->percent / 100;
                $priceWithOzonPercent = Ozon::getOzonPriceWithPercentByProduct($price, $OzonPercentByProduct);
            }

            foreach($costsDefaults as $CostsDefault)
            {
                $strDefaultValue = str_ireplace(
                    ['цена * ozon.процент', 'ozon.кластер', 'ozon.процент', 'TotalSalePrice', 'TotalWeightKg',    'ProductQuantity',    'кол.всего',  'ОРВЕС', 'цена'],
                    [$priceWithOzonPercent, $cluster, $OzonPercent1, $price, $Product->BoxSizes->valueWeightKg,     1,     1,   $Product->BoxSizes->estimatedWeight, $price],
                    $CostsDefault->default_value
                );

                switch($CostsDefault->value_type_id)
                {
                    case 1: // ₽
                        break;
                    case 2: // %
                            $strDefaultValue = $price . ' * ' .  round($strDefaultValue / 100, 3);
                        break;
                    case 3: // ƒ
                        break;
                }

                try{
                    $Delivery->value += eval("return $strDefaultValue;");
                } catch (\Exception $e) {}

                if($Delivery->formula) $Delivery->formula .= ' + ';
                $Delivery->formula .= "{$CostsDefault->cost->ozon_delivery_name}($strDefaultValue)";




                //dump($CostsDefault->default_value);
                //dump($strDefaultValue);
                //dump($Delivery->value);
                //dump('---');
            }
        }

        return $Delivery;
    }


    public static function actionProductPseudoQuantityTitle($shopId)
    {
        $title = 'Неизвестные остатки';

        switch((int) $shopId)
        {
            case 1: return 'Остаток склад СТВ+FBO';
            case 10001: return 'Остаток склад СТВ';
            case 2:case 10002: return 'Остаток склад МСК';
        }

        return $title;
    }

    public static function actionProductPseudoQuantity($Product, $shopId)
    {
        $available = WarehouseProductsAmount::where('product_id', $Product->id);

        switch((int) $shopId)
        {
            case 1:// для СТВ - "Остаток склад СТВ+FBO"
                    $available->whereIn('warehouse_id', array_merge([1], Shops::getShopWarehouses(74)->pluck('id')->toArray()));
                break;

            case 10001: // для СТВ2 - "Остаток склад СТВ"
                    $available->where('warehouse_id', 1);
                break;

            case 2:case 10002: // для МСК, МСК2 - "Остаток склад МСК"
                    $available->where('warehouse_id', 2);
                break;
        }

        $available = $available->sum('available');

        return $available?:0;
    }

    public static function saveOzonQuotas()
    {
        $shopIds = [1, 2, 10001, 10002];

        foreach($shopIds as $shopId)
        {
            $OzonAPI = self::getOzonApiByShopId($shopId);
            if($Limits = $OzonAPI->v3ProductInfoLimit())
            {
                $OzonQuota = new OzonQuota;
                $OzonQuota->shop_id = $shopId;
                $OzonQuota->create_quota_value = $Limits->daily_quota->create_quota_value??0;
                $OzonQuota->create_remaining_value = $Limits->daily_quota->create_remaining_value??0;
                $OzonQuota->all_quota_value = $Limits->daily_quota->all_quota_value??0;
                $OzonQuota->all_remaining_value = $Limits->daily_quota->all_remaining_value??0;
                $OzonQuota->reset_at = $Limits->daily_quota->reset_at??0;
                $OzonQuota->save();
            }
        }

        dump('ok');
    }
}


