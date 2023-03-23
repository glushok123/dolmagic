<?php

namespace App\Models;

use App\Console\Api\OzonApi;
use App\Console\Api\OzonApi2;
use App\Eloquent\Directories\Carrier;
use App\Eloquent\Directories\Cost;
use App\Eloquent\Directories\CostsDefault;
use App\Eloquent\Directories\FlowType;
use App\Eloquent\Directories\Income;
use App\Eloquent\Directories\ValueType;
use App\Eloquent\Order\OrdersTypeShop;
use App\Eloquent\Products\Product;
use App\Eloquent\Sales\Sale;
use App\Eloquent\Sales\SalesCost;
use App\Eloquent\Sales\SalesFinancesFromAPI;
use App\Eloquent\Sales\SalesFinancesFromFile;
use App\Eloquent\Sales\SalesFinancesService;
use App\Eloquent\Sales\SalesHistoriesType;
use App\Eloquent\Sales\SalesHistory;
use App\Eloquent\Sales\SalesIncome;
use App\Eloquent\Sales\SalesProduct;
use App\Eloquent\Sales\SalesProductsPack;
use App\Eloquent\Sales\SalesProductsStatus;
use App\Eloquent\System\SystemsDeliveryPrice;
use App\Eloquent\System\SystemsMarginCalcByStatus;
use App\Eloquent\System\SystemsMarginCalcCostsByStatus;
use App\Models\Directories\Costs;
use App\Models\Sales\SalesProducts;
use App\Models\Shops\ShopProducts;
use App\Models\Shops\Shops;
use App\Models\Users\Notifications;
use App\Models\Users\Users;
use App\Models\WarehouseProductsAmounts;
use App\Eloquent\Order\Order;
use App\Models\Products;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Model;

class Sales extends Model
{


    public static function historyAdd(int $saleId, $Model, $attr, $oldValue = false, $newValue = false, $ModelOld = false)
    {
        try {
            $userId = Auth::user()->id ?? 0;
            $SalesHistory = new SalesHistory;
            $SalesHistory->user_id = $userId;
            $SalesHistory->sale_id = $saleId;
            $SalesHistory->model_id = $Model->id;

            if ($oldValue !== false)
                $SalesHistory->old_value = $oldValue;
            if ($newValue !== false)
                $SalesHistory->new_value = $newValue;

            $SalesHistoriesType = SalesHistoriesType::where('alias', $attr)->first();
            if ($SalesHistoriesType) {
                $SalesHistory->type_id = $SalesHistoriesType->id;

                if (stripos($SalesHistoriesType->alias, 'sale-pack-') !== false) {
                    $SalesHistory->comment = "Упаковка номер $Model->number:";
                }

                if (stripos($SalesHistoriesType->alias, 'sale-product-') !== false) {
                    $SalesHistory->comment = 'Упаковка номер ' . $Model->pack->number . ', артикул ' . $Model->product->sku . ':';
                }

                if (stripos($SalesHistoriesType->alias, 'sale-cost-') !== false) {
                    $SalesHistory->comment = $Model->cost->name . ':';
                }

                if (stripos($SalesHistoriesType->alias, 'sale-income-') !== false) {
                    $SalesHistory->comment = $Model->income->name . ':';
                }

                switch ($SalesHistoriesType->alias)
                {
                    case 'sale-margin_value':
                        $SalesHistory->old_value = number_format($oldValue, 2, '.', '');
                        $SalesHistory->new_value = number_format($newValue, 2, '.', '');
                        break;
                    case 'sale-created':
                        $SalesHistory->comment = 'с датой продажи ' . $Model->date_sale;
                        break;
                    case 'sale-pack-carrier_id':
                        $OldCarrier = Carrier::where('id', $oldValue)->first();
                        if ($OldCarrier) $SalesHistory->old_value = $OldCarrier->name;

                        $NewCarrier = Carrier::where('id', $newValue)->first();
                        if ($NewCarrier) $SalesHistory->new_value = $NewCarrier->name;
                        break;
                    case 'sale-product-status_id':
                        $OldSalesProductsStatus = SalesProductsStatus::where('id', $oldValue)->first();
                        if ($OldSalesProductsStatus) $SalesHistory->old_value = $OldSalesProductsStatus->name;

                        $NewSalesProductsStatus = SalesProductsStatus::where('id', $newValue)->first();
                        if ($NewSalesProductsStatus) $SalesHistory->new_value = $NewSalesProductsStatus->name;

                        // check margin value

                        break;
                    case 'sale-finances_reconciled':
                            switch($SalesHistory->new_value)
                            {
                                case 0:
                                    $SalesHistory->old_value = 'Cверена';
                                    $SalesHistory->new_value = 'Не сверена';
                                    break;
                                case 1:
                                    $SalesHistory->old_value = 'Не сверена';
                                    $SalesHistory->new_value = 'Cверена';
                                    break;
                            }
                        break;

                    case 'sale-finances-from-file-created':
                    case 'sale-finances-from-file-deleted':
                        if($Service = $Model->service)
                        {
                            if($File = $Model->file)
                            {
                                $SalesHistory->comment = "$Service->name $Model->price ₽ из файла: $File->ATag";
                            }
                        }
                    break;

                    case 'sale-finances-from-file-reconciled':
                            if($Service = $Model->service)
                            {
                                $SalesHistory->comment = $Service->name;
                                switch($SalesHistory->new_value)
                                {
                                    case 0:
                                        $SalesHistory->old_value = 'Cверен';
                                        $SalesHistory->new_value = 'Не сверен';
                                        break;
                                    case 1:
                                        $SalesHistory->old_value = 'Не сверен';
                                        $SalesHistory->new_value = 'Cверен';
                                        break;
                                }
                            }

                        break;


                    case 'sale-product-product_id':
                        $OldProduct = Product::where('id', $oldValue)->first();
                        if ($OldProduct) $SalesHistory->old_value = $OldProduct->sku;

                        $NewProduct = Product::where('id', $newValue)->first();
                        if ($NewProduct) $SalesHistory->new_value = $NewProduct->sku;
                        break;

                    // Costs
                    case 'sale-cost-cost_id':
                        $OldCost = Cost::where('id', $oldValue)->first();
                        if ($OldCost) $SalesHistory->old_value = $OldCost->name;

                        $NewCost = Cost::where('id', $newValue)->first();
                        if ($NewCost) $SalesHistory->new_value = $NewCost->name;
                        break;
                    // Incomes
                    case 'sale-income-cost_id':
                        $OldIncome = Income::where('id', $oldValue)->first();
                        if ($OldIncome) $SalesHistory->old_value = $OldIncome->name;

                        $NewIncome = Income::where('id', $newValue)->first();
                        if ($NewIncome) $SalesHistory->new_value = $NewIncome->name;
                        break;

                    // Costs and incomes
                    case 'sale-cost-value_type_id':
                    case 'sale-income-value_type_id':
                        $OldValueType = ValueType::where('id', $oldValue)->first();
                        if ($OldValueType) $SalesHistory->old_value = $OldValueType->name;

                        $NewValueType = ValueType::where('id', $newValue)->first();
                        if ($NewValueType) $SalesHistory->new_value = $NewValueType->name;
                        break;
                    case 'sale-cost-flow_type_id':
                    case 'sale-income-flow_type_id':
                        $OldFlowType = FlowType::where('id', $oldValue)->first();
                        if ($OldFlowType) $SalesHistory->old_value = $OldFlowType->name;

                        $NewFlowType = FlowType::where('id', $newValue)->first();
                        if ($NewFlowType) $SalesHistory->new_value = $NewFlowType->name;
                        break;
                }
            }

            if (isset($SalesHistory->type_id)) $SalesHistory->save();
        } catch (\Exception $e) {
            self::log('error', 'historyAdd', $e->getTraceAsString());
        }
    }

    public static function create($Order = false)
    {
        $Sale = new Sale;
        if($Order)
        {
            if(!$Order->sale)
            {
                $Sale->order_id = $Order->id;
                $Sale->system_id = $Order->system_id;
                $Sale->type_shop_id = $Order->type_shop_id;

                if($OrderInfo = $Order->info)
                {
                    if($OrderInfo->shk_id) $Sale->shk_id = $OrderInfo->shk_id;
                    if($OrderInfo->sticker) $Sale->sticker_id = $OrderInfo->sticker;
                    if($OrderInfo->rid) $Sale->rid = $OrderInfo->rid;
                }
            } else {
                return ['error' => true, 'comment' => "Заказ $Order->id уже имеет продажу."];
            }
        };

        $Sale->save();

        if($Order) self::fillFromOrder($Sale, $Order);

        Costs::createAutoCostsForSale($Sale);

        $Sale->full_created = 1;
        $Sale->save();

        return $Sale;
    }

    public static function update($saleId)
    {
        self::log('error', 'sales::update', 'Empty function');
    }

    public static function cancel(Sale $Sale, $salesProductsStatusId = 3, $fromAnyStatus = false)
    {
        // cancel if all products in status id 1
        if($fromAnyStatus or ($Sale->products()->where([['status_id', '!=', 1]])->count() === 0))
        {
            foreach($Sale->products as $SaleProduct)
            {
                $SaleProduct->status_id = $salesProductsStatusId;
                $SaleProduct->auto_settled_status = true;
                $SaleProduct->save();
            }

            return true;
        }

        return false;
    }


    public static function saleReturn($Sale) // only WB now
    {
        if(!in_array($Sale->type_shop_id, Shops::getShopIdsByType('Wildberries')))
        {
            dump('only WB now');
            return false;
        }

        Sales::translateStatus($Sale, 4);

        $returnWarehouseId = false;
        switch($Sale->type_shop_id)
        {
            case 177:
                    $returnWarehouseId = 31;
                break;
            case 179:
                    $returnWarehouseId = 33;
                break;
        }

        if($returnWarehouseId)
        {
            $success = self::returnProductsToWarehouse($Sale, $returnWarehouseId);
        }
    }

    public static function returnProductsToWarehouse($Sale, $returnWarehouseId)
    {
        if($productsToReturn = self::getProductsToReturnToWarehouse($Sale, $returnWarehouseId))
        {
            $WarehouseMovement = WarehouseMovements::new(
                1,
                $returnWarehouseId,
                $productsToReturn,
                $Sale->order_id,
                $Sale->id,
                1
            );

            if($WarehouseMovement)
            {
                Sales::historyAdd(
                    $Sale->id,
                    $WarehouseMovement,
                    'auto-movement-2'
                );

                Notifications::notifyWBReturnProducts($Sale, [3]);
                return true;
            }else
            {
                Notifications::notifyWBReturnProductsError($Sale, [1, 3]);
                return false;
            }
        }

    }

    public static function getProductsToReturnToWarehouse($Sale, $returnWarehouseId)
    {
        $productsToReturnToWarehouse = [];

        if($productsBalance = self::getSaleMovementProductsBalance($Sale))
        {
            foreach($productsBalance as $productId => $productBalance)
            {
                if($productBalance < 0) // -1 or more
                {
                    $Product = new \stdClass();
                    $Product->product_id = $productId;
                    $Product->quantity = -$productBalance;
                    $Product->warehouse_id = $returnWarehouseId;

                    $SaleProduct = $Sale->products->where('product_id', $productId)->first();
                    $Product->price = $SaleProduct?ceil($SaleProduct->purchase_price):0;

                    $productsToReturnToWarehouse[] = $Product;
                }
            }
        }

        return $productsToReturnToWarehouse;
    }

    public static function getSaleMovementProductsBalance($Sale)
    {
        $productsBalance = [];

        foreach($Sale->movements as $SaleMovement)
        {
            //$SaleMovement->type
            foreach($SaleMovement->products as $SaleMovementProduct)
            {
                if($SaleMovementProduct->posting === 1)
                {
                    if(!isset($productsBalance[$SaleMovementProduct->product_id]))
                        $productsBalance[$SaleMovementProduct->product_id] = 0;

                    $productsBalance[$SaleMovementProduct->product_id] +=
                        ($SaleMovement->type * $SaleMovementProduct->product_quantity);
                }
            }
        }

        return $productsBalance;
    }

    public static function saleCancelOrReturnByMovement($Sale)
    {
        if($Sale->products_written_off or $Sale->sent_without_write_off) // second parameter OLEG 2021-11-12
        {
            Sales::cancel($Sale, 5, true); // return in road
        }else
        {
            Sales::cancel($Sale, 3, true); // just cancel
        }
    }

    public static function onOrderCancel(Order $Order, $OldSystemsOrdersStatusId = false)
    {
        if(isset($Order->sale))
        {
            self::saleCancelOrReturnByMovement($Order->sale);
        }
    }


    public static function fillFromOrder($Sale, $Order)
    {
        foreach($Order->packs as $OrderProductsPack)
        {
            $SalesProductsPack = new SalesProductsPack;
            $SalesProductsPack->sale_id = $Sale->id;
            $SalesProductsPack->number = $OrderProductsPack->number;
            $SalesProductsPack->save();

            foreach($OrderProductsPack->products as $OrderProduct)
            {
                $SalesProduct = new SalesProduct;
                $SalesProduct->sale_id = $Sale->id;
                $SalesProduct->pack_id = $SalesProductsPack->id;

                $SalesProduct->product_id = $OrderProduct->product_id;
                $SalesProduct->product_quantity = $OrderProduct->product_quantity;
                $SalesProduct->product_price = $OrderProduct->product_price;

                // set products status

                $SalesProduct->save();
            };

            if(in_array($Sale->type_shop_id, [71, 73, 174, 176])) // for Yandex DBS / FBS - set Delivery
            {
                self::updateIncomeByDeliveryPrice($Sale, $OrderProductsPack->delivery_price);
            }
        };
    }


    public static function calcTotalValueIfEachProduct($valueTypeId, $value, $saleProducts, $onlyAccountedStatus = false)
    {
        $totalValue = 0;
        foreach ($saleProducts as $SaleProduct)
        {
            if(!$onlyAccountedStatus or $SaleProduct->status->accounted_price)
            {
                switch ($valueTypeId)
                {
                    case 1:
                        $totalValue += $SaleProduct->product_quantity * $value;
                        break;
                    case 2:
                        $totalValue += $SaleProduct->product_price * $SaleProduct->product_quantity / 100 * $value;
                        break;
                }
            }
        }

        return $totalValue;
    }

    public static function getTotalDeliveryCost($Sale) // It's uses only for show result!!!
    {
        $totalDeliveryCost = 0;
        $deliveryIncomes = SalesIncome::where([['income_id', '=', 1], ['sale_id', '=', $Sale->id]])->get();

        foreach ($deliveryIncomes as $DeliveryIncome) {
            switch ($DeliveryIncome->flow_type_id) {
                case 1:
                    if ($DeliveryIncome->value_type_id === 1) {
                        $totalDeliveryCost += $DeliveryIncome->value;
                    } else {
                        $totalDeliveryCost += self::getTotalSalePrice($Sale, false) / 100 * $DeliveryIncome->value;
                    }
                    break;
                case 2:
                    $totalDeliveryCost += self::calcTotalValueIfEachProduct($DeliveryIncome->value_type_id, $DeliveryIncome->value, $Sale->products);
                    break;
            };
        }
        return $totalDeliveryCost;
    }

    public static function getTotalPrice($Sale) // It's uses only for show result!!!
    {
        $totalPrice = 0;
        foreach($Sale->products as $SaleProduct)
        {
            $totalPrice += $SaleProduct->product_price * $SaleProduct->product_quantity;
        }
        return $totalPrice;
    }

    public static function getTotalSalePrice($Sale, $onlyAccountedStatus = true)
    {
        $totalSalePrice = 0;
        foreach ($Sale->products as $SaleProduct) {
            if (!$onlyAccountedStatus or $SaleProduct->status->accounted_price) {
                $totalSalePrice += $SaleProduct->product_price * $SaleProduct->product_quantity;
            };
        }
        return $totalSalePrice;
    }

    public static function getTotalDiscount($Sale)
    {
        $totalDiscount = 0;
        $discounts = SalesCost::where([
            ['cost_id', '=', 12],
            ['sale_id', '=', $Sale->id]
        ])->get();

        foreach ($discounts as $Discount) {
            switch ($Discount->flow_type_id) {
                case 1:
                    if ($Discount->value_type_id === 1) {
                        $totalDiscount += $Discount->value;
                    } else {
                        $totalDiscount += self::getTotalSalePrice($Sale, false) / 100 * $Discount->value;
                    }
                    break;
                case 2:
                    $totalDiscount += self::calcTotalValueIfEachProduct($Discount->value_type_id, $Discount->value, $Sale->products);
                    break;
            };
        }
        return $totalDiscount;
    }

    public static function getTotalPayable($Sale)
    {

        $totalSalePrice = self::getTotalSalePrice($Sale);
        $totalDiscount = self::getTotalDiscount($Sale);
        $totalIncomes = self::getIncomesValue($Sale);
        $prepaymentMade = $Sale->prepayment_made;

        //весь товар (минус Скидка) + Доп.Доход (упаковка+доставка) - Предоплата!
        $totalPayable = $totalSalePrice - $totalDiscount + $totalIncomes - $prepaymentMade;

        return ($totalPayable);
    }

    public static function getTotalPurchasePrice($Sale)
    {
        $totalPurchasePrice = 0;
        foreach ($Sale->products as $SaleProduct) {
            if ($SaleProduct->status->count_purchase_price_sum) {
                $totalPurchasePrice += $SaleProduct->purchase_price * $SaleProduct->product_quantity;
            };
        }
        return $totalPurchasePrice;
    }

    public static function getIncomesValue($Sale) // It's uses only for show result!!!
    {
        $incomesValue = 0;
        foreach ($Sale->incomes as $SaleIncome) {
            switch ($SaleIncome->flow_type_id) {
                case 1: // all order
                    if ($SaleIncome->value_type_id === 1) {
                        $incomesValue += $SaleIncome->value;
                    } else {
                        $incomesValue += self::getTotalSalePrice($Sale, false) / 100 * $SaleIncome->value;
                    }
                    break;
                case 2: // each product
                    $incomesValue += self::calcTotalValueIfEachProduct($SaleIncome->value_type_id, $SaleIncome->value, $Sale->products);
                    break;
            };
        }
        return $incomesValue;
    }

    public static function getCostsValue($Sale) // It's uses only for show result!!!
    {
        $costsValue = 0;
        foreach ($Sale->costs as $SaleCost) {
            if ($SaleCost->cost->is_used === 1) {
                switch ($SaleCost->flow_type_id) {
                    case 1:
                        if ($SaleCost->value_type_id === 1) {
                            $costsValue += $SaleCost->value;
                        } else {
                            $costsValue += self::getTotalSalePrice($Sale, false) / 100 * $SaleCost->value;
                        }
                        break;
                    case 2:
                        $costsValue += self::calcTotalValueIfEachProduct($SaleCost->value_type_id, $SaleCost->value, $Sale->products);
                        break;
                };
            }
        }
        return $costsValue;
    }


    public static function getIncomeOrCostValue($IncomeOrCost, $Sale = false, $onlyAccountedStatus = false)
    {
        if(!$Sale) $Sale = $IncomeOrCost->sale;
        $total = 0;
        switch ($IncomeOrCost->flow_type_id) {
            case 1:
                if ($IncomeOrCost->value_type_id === 1) {
                    $total = $IncomeOrCost->value;
                } else {
                    $total = self::getTotalSalePrice($Sale, $onlyAccountedStatus) * $IncomeOrCost->value / 100;
                }
                break;
            case 2:
                $total = self::calcTotalValueIfEachProduct($IncomeOrCost->value_type_id, $IncomeOrCost->value, $Sale->products);
                break;
        };

        return $total;
    }

    public static function getTotalIncomeOrCostValue($IncomeOrCosts, $Sale)
    {
        $total = 0;
        foreach ($IncomeOrCosts as $IncomeOrCost) {
            $total += self::getIncomeOrCostValue($IncomeOrCost, $Sale);
        }
        return $total;
    }

    public static function checkSaleStatusReturn($Sale)
    {
        $countReturnProducts = SalesProduct::where([['sale_id', '=', $Sale->id]])->whereNotIn('status_id', [4, 5])->count();
        return $countReturnProducts === 0;
    }

    public static function getOutlayList($Sale)
    {
        $Outlays = [];
        $costIds = false;

        if(isset($Sale->order))
        {
            switch($Sale->order->typeShop->id)
            {
                case 1: // OzonSTV
                    $costIds = [14, 15];
                    break;
                case 2: // OzonMSK
                    $costIds = [1, 11, 14, 15];
                    break;
                case 3: // InSales
                case 4: // ТИУ
                    $costIds = [11, 13];
                    break;
                case 5: // Goods
                    $costIds = [1];
                    break;
            }
        }

        if ($costIds) {
            $Outlays = SalesCost::where([['sale_id', '=', $Sale->id]])->whereIn('cost_id', $costIds)->get();
        }

        return $Outlays;
    }

    public static function getMarginValue($Sale) // calc margin value
    {
        $returnStatus = self::checkSaleStatusReturn($Sale);

        if ($returnStatus) {
            $outlayList = self::getOutlayList($Sale);
            $marginValue = -self::getTotalIncomeOrCostValue($outlayList, $Sale);
        } else {
            $saleTotalPrice = self::getTotalSalePrice($Sale);
            $salePurchasePrice = self::getTotalPurchasePrice($Sale);
            $saleCommission = self::getCommission($Sale);

            $marginValue = $saleTotalPrice - $salePurchasePrice - $saleCommission + $Sale->incomesValue - $Sale->costsValue;
        }

        return $marginValue;
    }

    public static function getSaleProductMargin($SaleProduct)
    {
        $Sale = $SaleProduct->sale;

        $AllAmountsValue = new \stdClass();
        $AllAmountsValue->price = 0;
        $AllAmountsValue->purchasePrice = 0;
        $AllAmountsValue->commission = 0;
        $AllAmountsValue->incomes = 0;
        $AllAmountsValue->costs = 0;
        $AllAmountsValue->margin = 0;
        $AllAmountsValue->TotalSaleTotalPriceWithoutCommission = 0;

        $MarginCalc = SystemsMarginCalcByStatus::where([
            ['type_shop_id', $Sale->type_shop_id],
            ['sales_products_status_id', $SaleProduct->status_id]
        ])->first();

        if($MarginCalc)
        {
            if ($MarginCalc->price) $AllAmountsValue->price += $SaleProduct->product_price * $SaleProduct->product_quantity;
            if ($MarginCalc->purchase_price) $AllAmountsValue->purchasePrice += $SaleProduct->purchase_price * $SaleProduct->product_quantity;

            if ($MarginCalc->commission) $AllAmountsValue->commission += SalesProducts::getCommission($SaleProduct) * $SaleProduct->product_quantity;

            if ($MarginCalc->costs)
                $AllAmountsValue->costs += SalesProducts::getCostsValue($SaleProduct, $Sale);

            if ($MarginCalc->incomes)
                $AllAmountsValue->incomes += SalesProducts::getIncomesValue($SaleProduct, $Sale);
        }

        //Маржа = Цена продажи - Закупочная цена - Комиссия площадки + Доп.доходы - Доп.расходы
        $AllAmountsValue->margin =
        +$AllAmountsValue->price
        - $AllAmountsValue->purchasePrice
        - $AllAmountsValue->commission
        + $AllAmountsValue->incomes
        - $AllAmountsValue->costs;

        return $AllAmountsValue->margin;
    }

    public static function getMarginPercent($Sale)
    {
        $totalMargin = 0;
        $TotalPurchasePrice = 0;
        $res = 0;
        foreach($Sale->products as $SaleProduct)
        {
            if(($SaleProduct->purchase_price > 0) and in_array($SaleProduct->status_id, [1, 2, 8])) //Заказ, Доставлено, Продано
            {
                $totalMargin += Sales::getSaleProductMargin($SaleProduct);
                $TotalPurchasePrice += $SaleProduct->TotalPurchasePrice;
            }
        }
        if($TotalPurchasePrice > 0) $res = round($totalMargin / $TotalPurchasePrice * 100, 2);
        return $res;
    }

    public static function getAllAmountsValue($Sale)
    {
        $AllAmountsValue = new \stdClass();
        $AllAmountsValue->price = 0;
        $AllAmountsValue->purchasePrice = 0;
        $AllAmountsValue->commission = 0;
        $AllAmountsValue->incomes = 0;
        $AllAmountsValue->costs = 0;
        $AllAmountsValue->margin = 0;
        $AllAmountsValue->TotalSaleTotalPriceWithoutCommission = 0;

        foreach($Sale->products as $SaleProduct)
        {
            // ???
            $AllAmountsValue->TotalSaleTotalPriceWithoutCommission += ($SaleProduct->TotalPriceWithoutCommission??0);

            $MarginCalc = SystemsMarginCalcByStatus::where([
                ['type_shop_id', $Sale->type_shop_id],
                ['sales_products_status_id', $SaleProduct->status_id]
            ])->first();

            if ($MarginCalc)
            {
                if ($MarginCalc->price) $AllAmountsValue->price += $SaleProduct->product_price * $SaleProduct->product_quantity;;
                if ($MarginCalc->purchase_price) $AllAmountsValue->purchasePrice += $SaleProduct->purchase_price * $SaleProduct->product_quantity;

                if ($MarginCalc->commission) $AllAmountsValue->commission += SalesProducts::getCommission($SaleProduct) * $SaleProduct->product_quantity;


                // commission_deduction ?????

                if ($MarginCalc->costs)
                    $AllAmountsValue->costs += SalesProducts::getCostsValue($SaleProduct, $Sale);

                if ($MarginCalc->incomes)
                    $AllAmountsValue->incomes += SalesProducts::getIncomesValue($SaleProduct, $Sale);
            }
        }

        //Маржа = Цена продажи - Закупочная цена - Комиссия площадки + Доп.доходы - Доп.расходы
        $AllAmountsValue->margin =
            +$AllAmountsValue->price
            - $AllAmountsValue->purchasePrice
            - $AllAmountsValue->commission
            + $AllAmountsValue->incomes
            - $AllAmountsValue->costs;

        return $AllAmountsValue;
    }

    public static function getSale($saleId)
    {
        $clauses = ['id' => $saleId];
        $Sale = Sale::where($clauses)->first();
        return $Sale ?? false;
    }

    public static function getSalesProductsPack($packId)
    {
        $clauses = ['id' => $packId];
        $SalesProductsPack = SalesProductsPack::where($clauses)->first();
        return $SalesProductsPack ?? false;
    }

    public static function getSalesProduct($salesProductId)
    {
        $clauses = ['id' => $salesProductId];
        $SalesProduct = SalesProduct::where($clauses)->first();
        return $SalesProduct ?? false;
    }

    public static function getSalesCost($SalesCostId)
    {
        $clauses = ['id' => $SalesCostId];
        $SalesCost = SalesCost::where($clauses)->first();
        return $SalesCost ?? false;
    }

    public static function getSalesIncome($SalesIncomeId)
    {
        $clauses = ['id' => $SalesIncomeId];
        $SalesIncome = SalesIncome::where($clauses)->limit(1)->first();
        return $SalesIncome ?? false;
    }


    /* From user side */
    public static function getPacksFromForm($request)
    {
        $objPacks = [];
        $formPacks = $request->input('packs', NULL);
        if ($formPacks and (count($formPacks) > 1)) {
            foreach ($formPacks as $formPack) {
                if ($formPack['pack_id'] == 'pack-clone') continue;

                $Pack = (object)$formPack;

                if (!isset($Pack->products)) return ['error' => true, 'comment' => "В упаковке $Pack->pack_num нет продуктов."];

                $objProducts = [];
                foreach ($Pack->products as $formProduct) {
                    if (isset($formProduct['id'])) {
                        $product_id = ((int)$formProduct['id']);
                        $Product = Products::getProductBy('id', $product_id);
                        if (!$Product) {
                            return ['error' => true, 'comment' => "В упаковке $Pack->pack_num найден продукт, который не найден в базе."];
                        } else {
                            $objProducts[] = (object)$formProduct;
                        }
                    } else {
                        return ['error' => true, 'comment' => 'В упаковке найден неизвестный продукт.'];
                    };
                }
                $Pack->products = $objProducts;
                $objPacks[] = $Pack;
            }
            return $objPacks;
        } else {
            return ['error' => true, 'comment' => 'Не найдены упаковки для продажи. (Код 1)'];
        }
    }

    public static function getCostsFromForm($request)
    {
        $objCosts = [];
        $formCosts = $request->input('saleCost', NULL);
        if ($formCosts) {
            foreach ($formCosts as $formCost) {
                if (!isset($formCost['cost_id'])) continue;
                $objCosts[] = (object)$formCost;
            }
        };
        return $objCosts;
    }

    public static function getIncomesFromForm($request)
    {
        $objIncomes = [];
        $formIncomes = $request->input('saleIncome', NULL);
        if ($formIncomes) {
            foreach ($formIncomes as $formIncome) {
                if (!isset($formIncome['income_id'])) continue;
                $objIncomes[] = (object)$formIncome;
            }
        };
        return $objIncomes;
    }

    public static function createSalesPackProductsFromForm($Sale, $SalesProductsPack, $formProducts)
    {
        foreach ($formProducts as $FormProduct) {
            $SalesProduct = new SalesProduct;
            $SalesProduct->sale_id = $Sale->id;
            $SalesProduct->pack_id = $SalesProductsPack->id;

            $SalesProduct->product_id = $FormProduct->id;
            $SalesProduct->product_quantity = $FormProduct->quantity;
            $SalesProduct->product_price = $FormProduct->price;
            $SalesProduct->purchase_price = $FormProduct->purchase_price;

            $SalesProduct->commission_value = $FormProduct->commission_value;
            $SalesProduct->commission_percent = $FormProduct->commission_percent;

            if (isset($FormProduct->commission_min_value))
                $SalesProduct->commission_min_value = $FormProduct->commission_min_value;

            $SalesProduct->status_id = $FormProduct->status_id;

            if ($SalesProduct->save()) {
            } else {
                return ['error' => true, 'comment' => "Товара ID $FormProduct->id  не сохранился. (Код 1)"];
            }
        }
        return true;
    }

    public static function createSalesPackFromForm($Sale, $FormPack, $number)
    {
        $SalesProductsPack = new SalesProductsPack;
        $SalesProductsPack->sale_id = $Sale->id;
        $SalesProductsPack->number = $FormPack->pack_num ?? $number;

        if (isset($FormPack->carrier_id)) $SalesProductsPack->carrier_id = $FormPack->carrier_id;
        //if (isset($FormPack->delivery_address)) $SalesProductsPack->delivery_address = $FormPack->delivery_address;
        if (isset($FormPack->cost_id)) $SalesProductsPack->cost_id = $FormPack->cost_id;
        if (isset($FormPack->track_number)) $SalesProductsPack->track_number = $FormPack->track_number;

        if (isset($FormPack->delivery_date)) $SalesProductsPack->delivery_date = $FormPack->delivery_date;
        if (isset($FormPack->departure_date)) $SalesProductsPack->departure_date = $FormPack->departure_date;
        if (isset($FormPack->planned_delivery_date)) $SalesProductsPack->planned_delivery_date = $FormPack->planned_delivery_date;

        if ($SalesProductsPack->save()) {
            $created = self::createSalesPackProductsFromForm($Sale, $SalesProductsPack, $FormPack->products);
            if (isset($created['error'])) return $created;
        } else {
            return ['error' => true, 'comment' => 'Упаковка продажи не добавилась. (Код 1)'];
        }

        return true;
    }

    public static function createSalesPacksFromForm($Sale, $formPacks)
    {
        foreach ($formPacks as $number => $FormPack) {
            $created = self::createSalesPackFromForm($Sale, $FormPack, $number);
            if (isset($created['error'])) return $created;
        }

        return true;
    }

    public static function createSalesCostsFromForm($Sale, $formCosts)
    {
        foreach ($formCosts as $FormCost)
        {
            $SalesCost = new SalesCost;
            $SalesCost->sale_id = $Sale->id;
            $SalesCost->cost_id = $FormCost->cost_id;
            $SalesCost->comment = $FormCost->comment;
            $SalesCost->value = $FormCost->value;
            $SalesCost->value_type_id = $FormCost->value_type_id;
            $SalesCost->flow_type_id = $FormCost->flow_type_id;
            if ($SalesCost->save()) {
            } else {
                return ['error' => true, 'comment' => 'Расход не создался. (Код 1)'];
            }
        }
        return true;
    }

    public static function createSalesIncomesFromForm($Sale, $formIncomes)
    {
        foreach ($formIncomes as $FormIncome) {
            $SalesIncome = new SalesIncome();
            $SalesIncome->sale_id = $Sale->id;
            $SalesIncome->income_id = $FormIncome->income_id;
            $SalesIncome->comment = $FormIncome->comment;
            $SalesIncome->value = $FormIncome->value;
            $SalesIncome->value_type_id = $FormIncome->value_type_id;
            $SalesIncome->flow_type_id = $FormIncome->flow_type_id;
            if ($SalesIncome->save()) {
            } else {
                return ['error' => true, 'comment' => 'Доход не создался. (Код 1)'];
            }
        }
        return true;
    }


    public static function newFromForm($ordersTypeShopId, $orderId, $manualOrderNumber, $comments, $prepaymentMade, $dateSale, $formCosts, $formIncomes, $formPacks)
    {
        $Order = Orders::getOrder($orderId);
        if ($Order) return ['error' => true, 'comment' => 'Для заказа уже существует продажа. (Код 1)'];

        if (!$formPacks) return ['error' => true, 'comment' => 'Не найдены упаковки для продажи. (Код 2)'];

        $Sale = new Sale;
        if ($ordersTypeShopId) $Sale->type_shop_id = $ordersTypeShopId;
        if ($ordersTypeShopId) $Sale->system_id = OrdersTypeShop::where('id', $ordersTypeShopId)->first()->system_id;
        if ($orderId) $Sale->order_id = $orderId;
        if ($manualOrderNumber) $Sale->manual_order_number = $manualOrderNumber;
        if ($comments) $Sale->comments = $comments;
        if ($dateSale) $Sale->date_sale = $dateSale;
        $Sale->prepayment_made = $prepaymentMade;
        $Sale->save();

        $createdPacks = self::createSalesPacksFromForm($Sale, $formPacks);
        $createdCosts = self::createSalesCostsFromForm($Sale, $formCosts);
        $createdIncomes = self::createSalesIncomesFromForm($Sale, $formIncomes);

        if (isset($createdPacks['error']) or isset($createdCosts['error']) or isset($createdIncomes['error'])) {
            $Sale->products()->delete();
            $Sale->packs()->delete();
            $Sale->incomes()->delete();
            $Sale->costs()->delete();
            $Sale->delete();

            return $createdPacks['error'] ?? ($createdCosts['error'] ?? $createdIncomes['error']);
        };

        Costs::createAutoCostsForSale($Sale);

        $Sale->full_created = 1;

        $Sale->save();

        return $Sale;
    }

    public static function updateSalesPackProductsFromForm($Sale, $SalesProductsPack, $formProducts)
    {
        foreach ($formProducts as $FormProduct) {
            if (isset($FormProduct->sale_product_id)) {
                $SalesProduct = self::getSalesProduct($FormProduct->sale_product_id);
                if (!$SalesProduct) return ['error' => true, 'comment' => "Товара продажи sale_product_id $FormProduct->sale_product_id : id FormProduct->id  не найдено в базе. (Код 364)"];
            } else {
                $SalesProduct = new SalesProduct;
            };

            $SalesProduct->sale_id = $Sale->id;
            $SalesProduct->pack_id = $SalesProductsPack->id;

            $SalesProduct->product_id = $FormProduct->id;
            $SalesProduct->product_quantity = $FormProduct->quantity;
            $SalesProduct->product_price = $FormProduct->price;
            $SalesProduct->purchase_price = $FormProduct->purchase_price;

            $SalesProduct->commission_value = $FormProduct->commission_value;
            $SalesProduct->commission_percent = $FormProduct->commission_percent;

            if (isset($FormProduct->commission_min_value))
                $SalesProduct->commission_min_value = $FormProduct->commission_min_value;

            $SalesProduct->status_id = $FormProduct->status_id;
            $SalesProduct->auto_settled_status = false;

            if ($SalesProduct->save()) {
            } else {
                return ['error' => true, 'comment' => "Товар ID $FormProduct->id  не сохранился. (Код 1)"];
            }
        }
        return true;
    }

    public static function updateSalesPackFromForm($Sale, $SalesProductsPack, $FormPack, $number)
    {
        if(isset($FormPack->cost_id)) $SalesProductsPack->cost_id = $FormPack->cost_id;
        if(isset($FormPack->logistic_order)) $SalesProductsPack->logistic_order = $FormPack->logistic_order;

        $SalesProductsPack->carrier_id = $FormPack->carrier_id;
        //$SalesProductsPack->delivery_address = $FormPack->delivery_address;
        //$SalesProductsPack->track_number = $FormPack->track_number;

        $SalesProductsPack->delivery_date = $FormPack->delivery_date;
        $SalesProductsPack->departure_date = $FormPack->departure_date;
        $SalesProductsPack->planned_delivery_date = $FormPack->planned_delivery_date;


        if ($SalesProductsPack->save()) {
            $updated = self::updateSalesPackProductsFromForm($Sale, $SalesProductsPack, $FormPack->products);
            if (isset($updated['error'])) return $updated;
        } else {
            return ['error' => true, 'comment' => 'Упаковка продажи не сохранилась. (Код 1)'];
        }

        return true;
    }

    public static function updateSalesPacksFromForm($Sale, $formPacks)
    {
        foreach ($formPacks as $number => $FormPack) {
            $SalesProductsPack = $FormPack->pack_id ? self::getSalesProductsPack($FormPack->pack_id) : false;
            if ($SalesProductsPack) {
                $updated = self::updateSalesPackFromForm($Sale, $SalesProductsPack, $FormPack, $number);
            } else {
                $updated = self::createSalesPackFromForm($Sale, $FormPack, $number);
            };
            if (isset($updated['error'])) return $updated;
        }

        return true;
    }

    public static function updateSalesCostsFromForm($Sale, $formCosts)
    {
        foreach($formCosts as $FormCost)
        {
            if(isset($FormCost->sales_cost_id))
            {
                $SalesCost = self::getSalesCost($FormCost->sales_cost_id);
                if (!$SalesCost) return ['error' => true, 'comment' => 'Уже сохранённый расход не найден в базе. (Код 432)'];
            } else {
                $SalesCost = new SalesCost;
            }

            if($SalesCost->value != $FormCost->value)
            {
                $SalesCost->last_user_id = Users::getCurrent()->id;
            }

            $SalesCost->sale_id = $Sale->id;
            $SalesCost->cost_id = $FormCost->cost_id;
            $SalesCost->comment = $FormCost->comment;
            $SalesCost->value = $FormCost->value;
            $SalesCost->value_type_id = $FormCost->value_type_id;
            $SalesCost->flow_type_id = $FormCost->flow_type_id;

            if($SalesCost->save())
            {

            }else{
                return ['error' => true, 'comment' => 'Расход продажи не сохранился. (Код 443)'];
            }
        }
        return true;
    }

    public static function updateSalesIncomesFromForm($Sale, $formIncomes)
    {
        foreach ($formIncomes as $FormIncome) {
            if (isset($FormIncome->sales_income_id)) {
                $SalesIncome = self::getSalesIncome($FormIncome->sales_income_id);
                if (!$SalesIncome) return ['error' => true, 'comment' => 'Уже сохранённый доход не найден в базе - ошибка его обновления. (Код 454)'];
            } else {
                $SalesIncome = new SalesIncome();
            }

            $SalesIncome->sale_id = $Sale->id;
            $SalesIncome->income_id = $FormIncome->income_id;
            $SalesIncome->comment = $FormIncome->comment;
            $SalesIncome->value = $FormIncome->value;
            $SalesIncome->value_type_id = $FormIncome->value_type_id;
            $SalesIncome->flow_type_id = $FormIncome->flow_type_id;
            if ($SalesIncome->save()) {
            } else {
                return ['error' => true, 'comment' => 'Доход не сохранился. (Код 466)'];
            }
        }
        return true;
    }

    public static function updateFromForm(
        $saleId,
        $manualOrderNumber,
        $comments,
        $prepaymentMade,
        $dateSale,
        $formCosts,
        $formIncomes,
        $formPacks,
        $sp_sale,
        $self_redemption
    )
    {
        $Sale = Sales::getSale($saleId);
        if (!$Sale) return ['error' => true, 'comment' => 'Продажа не найдена, возможно, её уже удалили. (Код 1)'];
        if (!$formPacks) return ['error' => true, 'comment' => 'Не найдены упаковки для продажи. (Код 3)'];

        $updatedCosts = self::updateSalesCostsFromForm($Sale, $formCosts);
        $updatedIncomes = self::updateSalesIncomesFromForm($Sale, $formIncomes);
        $updatedPacks = self::updateSalesPacksFromForm($Sale, $formPacks);

        if (isset($updatedPacks['error']) or isset($updatedCosts['error']) or isset($updatedIncomes['error'])) {
            return $updatedPacks['error'] ?? ($updatedCosts['error'] ?? $updatedIncomes['error']);
        };

        if ($comments) $Sale->comments = $comments;
        if ($manualOrderNumber) $Sale->manual_order_number = $manualOrderNumber;
        $Sale->prepayment_made = $prepaymentMade;
        if($dateSale) $Sale->date_sale = $dateSale;

        $Sale->sp_sale = (bool) $sp_sale;
        $Sale->self_redemption = (bool) $self_redemption;

        $Sale->save();

        return $Sale;
    }

    public static function removeProductsPack($packId)
    {
        $SalesProductsPack = self::getSalesProductsPack($packId);
        if ($SalesProductsPack) {
            $SalesProductsPack->products()->delete();
            $SalesProductsPack->delete();
            return true;
        } else {
            return ['error' => true, 'comment' => 'Невозможно удалить: упаковка продажи не найдена, возможно, она уже удалена.'];
        }
    }

    public static function removeSaleProduct($salesProductId)
    {
        $SalesProduct = self::getSalesProduct($salesProductId);
        if ($SalesProduct) {
            $SalesProduct->delete();
            return true;
        } else {
            return ['error' => true, 'comment' => 'Невозможно удалить: продукт продажи не найден, возможно, он уже удален.'];
        }
    }

    public static function removeSaleCost($salesCostId)
    {
        $SalesCost = self::getSalesCost($salesCostId);
        if ($SalesCost) {
            $SalesCost->delete();
            return true;
        } else {
            return ['error' => true, 'comment' => 'Невозможно удалить: расход продажи не найден, возможно, он уже удален.'];
        }
    }

    public static function removeSaleIncome($salesIncomeId)
    {
        $SalesIncome = self::getSalesIncome($salesIncomeId);
        if ($SalesIncome) {
            $SalesIncome->delete();
            return true;
        } else {
            return ['error' => true, 'comment' => 'Невозможно удалить: доход продажи не найден, возможно, он уже удален.'];
        }
    }

    public static function remove($saleId)
    {
        $Sale = self::getSale($saleId);
        if ($Sale) {
            $Sale->products()->delete();
            $Sale->packs()->delete();
            $Sale->incomes()->delete();
            $Sale->costs()->delete();
            $Sale->delete();
            return true;
        } else {
            return ['error' => true, 'comment' => 'Невозможно удалить: продажа не найдена, возможно, она уже удалена.'];
        }
    }

    public static function getSystemDeliveryRub($Sale)
    {
        $systemCommission = new \stdClass();
        $systemCommission->valueRub = 0;

        foreach ($Sale->costs as $SaleCost) {
            if ($Sale->system_id === 3) {
                if ($SaleCost->cost_id === 14) // Ozon delivery
                {
                    try {
                        $systemCommission->valueRub += self::getIncomeOrCostValue($SaleCost, $Sale);
                    } catch (\Exception $e) {
                        self::log('error', 'getSystemDeliveryRub', 'id: ' . $SaleCost . ' unknown error 2');
                    }
                }
            }
        }

        return $systemCommission->valueRub;
    }

    public static function getSystemDeliveryReturnRub($Sale)
    {
        $systemCommission = new \stdClass();
        $systemCommission->valueRub = 0;

        foreach ($Sale->costs as $SaleCost) {
            if ($Sale->system_id === 3) {
                if ($SaleCost->cost_id === 15) // Ozon delivery return
                {
                    try {
                        $systemCommission->valueRub += self::getIncomeOrCostValue($SaleCost, $Sale);
                    } catch (\Exception $e) {
                        self::log('error', 'getSystemDeliveryReturnRub', 'id: ' . $SaleCost . ' unknown error 2');
                    }
                }
            }
        }

        return $systemCommission->valueRub;
    }


    public static function applyFilter(&$Sales, $Filter)
    {
        if(isset($Filter->sp_sale) and $Filter->sp_sale)
        {
            switch($Filter->sp_sale)
            {
                case -1:
                    $Sales->where('sp_sale', '!=', true);
                    break;
                case 1:
                    $Sales->where('sp_sale', '=', true);
                    break;
            }
        }

        if(isset($Filter->self_redemption) and $Filter->self_redemption)
        {
            switch($Filter->self_redemption)
            {
                case -1:
                        $Sales->where('self_redemption', '!=', true);
                    break;
                case 1:
                        $Sales->where('self_redemption', '=', true);
                    break;
            }
        }

        if(isset($Filter->ritmz_number) and $Filter->ritmz_number)
        {
            $Sales->whereHas('order.info', function($q) use ($Filter)
            {
                $q->where('ritmz_number', 'LIKE', "%$Filter->ritmz_number%");
            });
        }

        if($Filter->numberKey)
        {
            $Sales->where(function ($q) use ($Filter)
            {
                $q->whereHas('order', function ($q) use ($Filter) {
                    if ($Filter->numberKeyEqual) {
                        $q->where('order_system_number', '=', "$Filter->numberKey");
                    } else {
                        $q->where('order_system_number', 'LIKE', "%$Filter->numberKey%");
                    };
                });

                if ($Filter->numberKeyEqual)
                {
                    $q->orWhere('manual_order_number', '=', "$Filter->numberKey");
                } else {
                    $q->orWhere('manual_order_number', 'LIKE', "%$Filter->numberKey%");
                };

                $q->orWhereHas('packs', function($q) use ($Filter){
                    if ($Filter->numberKeyEqual) {
                        $q->where('logistic_order', '=', "$Filter->numberKey");
                    } else {
                        $q->where('logistic_order', 'LIKE', "%$Filter->numberKey%");
                    };
                });

                $q->orWhereHas('order.info', function($q) use ($Filter){
                    if ($Filter->numberKeyEqual) {
                        $q->where('ritmz_number', '=', "$Filter->numberKey");
                    } else {
                        $q->where('ritmz_number', 'LIKE', "%$Filter->numberKey%");
                    };
                });

                $q->orWhere(function($q) use ($Filter)
                {
                    if ($Filter->numberKeyEqual) {
                        $q->where('rid', '=', "$Filter->numberKey");
                    } else {
                        $q->where('rid', 'LIKE', "%$Filter->numberKey%");
                    };
                });

                $q->orWhere(function($q) use ($Filter)
                {
                    if ($Filter->numberKeyEqual) {
                        $q->where('sticker_id', '=', "$Filter->numberKey");
                    } else {
                        $q->where('sticker_id', 'LIKE', "%$Filter->numberKey%");
                    };
                });

                $q->orWhere(function($q) use ($Filter)
                {
                    if ($Filter->numberKeyEqual) {
                        $q->where('shk_id', '=', "$Filter->numberKey");
                    } else {
                        $q->where('shk_id', 'LIKE', "%$Filter->numberKey%");
                    };
                });

                $q->orWhere(function($q) use ($Filter)
                {
                    if ($Filter->numberKeyEqual) {
                        $q->where('id', '=', "$Filter->numberKey");
                    } else {
                        $q->where('id', 'LIKE', "%$Filter->numberKey%");
                    };
                });
            });
        };

        if($Filter->dateOrderFrom or $Filter->dateOrderTo)
        {
            $Sales->whereHas('order', function ($q) use ($Filter)
            {
                $q->whereHas('info', function ($q) use ($Filter)
                {
                    if ($Filter->dateOrderFrom)
                        $q->where("order_date_create", ">=", Carbon::parse($Filter->dateOrderFrom, 'Europe/Moscow')->startOfDay()->setTimezone('UTC')->toDateTimeString());

                    if ($Filter->dateOrderTo)
                        $q->where("order_date_create", "<=", Carbon::parse($Filter->dateOrderTo, 'Europe/Moscow')->endOfDay()->setTimezone('UTC')->toDateTimeString());
                });
            });
        }

        if ($Filter->dateSaleFrom) $Sales->where("date_sale", ">=", Carbon::parse($Filter->dateSaleFrom)->startOfDay()->toDateTimeString());
        if ($Filter->dateSaleTo) $Sales->where("date_sale", "<=", Carbon::parse($Filter->dateSaleTo)->endOfDay()->toDateTimeString());

        if($Filter->packDepartureDateFrom or $Filter->packDepartureDateTo)
        {
            $Sales->whereHas('packs', function ($q) use ($Filter)
            {
                if ($Filter->packDepartureDateFrom) $q->where('departure_date', '>=', Carbon::parse($Filter->packDepartureDateFrom)->startOfDay()->toDateTimeString());
                if ($Filter->packDepartureDateTo) $q->where('departure_date', '<=', Carbon::parse($Filter->packDepartureDateTo)->endOfDay()->toDateTimeString());
            });
        }

        // ordersTypeShops (orders_type_shop_ids) (need to optimise)
        $Filter->ordersTypeShopIds = (array) $Filter->ordersTypeShopIds;
        if(count($Filter->ordersTypeShopIds) > 0)
        {
            $Sales->whereIn('type_shop_id', $Filter->ordersTypeShopIds);
        };

        if(isset($Filter->upon_receipt) and ($Filter->upon_receipt !== '-1'))
        {
            $Sales->whereHas('order', function ($order) use ($Filter)
            {
                $order->whereHas('info', function ($orderInfo) use ($Filter)
                {
                    $orderInfo->whereHas('paymentType', function ($orderPaymentType) use ($Filter)
                    {
                        $orderPaymentType->where('upon_receipt', $Filter->upon_receipt);
                    });
                });
            });
        }

        $Filter->carriersIds = (array) $Filter->carriersIds;
        if (count($Filter->carriersIds) > 0)
        {
            $carriersByIds = Carrier::whereIn('id', $Filter->carriersIds)->get();
            $Sales->whereHas('packs', function ($q) use ($carriersByIds) {
                foreach ($carriersByIds as $key => $CarrierById) {
                    if (!$key) {
                        $q->where("carrier_id", $CarrierById->id);
                    } else {
                        $q->orWhere("carrier_id", $CarrierById->id);
                    }
                }
            });
        };

        if (!empty($Filter->showSalesWithProductsStatusesIds) or !empty($Filter->hideSalesWithProductsStatusesIds))
        {
            $Sales->whereHas('products', function ($q) use ($Filter)
            {
                if (!empty($Filter->showSalesWithProductsStatusesIds))
                    $q->whereIn('status_id', $Filter->showSalesWithProductsStatusesIds);

                if (!empty($Filter->hideSalesWithProductsStatusesIds))
                    $q->whereNotIn('status_id', $Filter->hideSalesWithProductsStatusesIds);
            });
        }

        //End Sale statuses

        if($Filter->nonePurchasePrice)
        {
            $Sales->whereHas('products', function ($q) use ($Filter)
            {
                $q->where('purchase_price', '=', 0); // Where products have something other than CANCEL
            });
        }

        if($Filter->noneCostDelivery)
        {
            $Sales->where(function($q)
            {
                $q->where(function($q2)
                {
                    $q2->where('type_shop_id', 1) // Ozon STV
                        ->whereDoesntHave('costs', function ($q){
                            $q->whereIn('cost_id', [11, 14]); // Delivery
                        });
                })->orWhere(function($q2)
                {
                    $q2->where('type_shop_id', 2) // Ozon MSK
                    ->whereDoesntHave('costs', function ($q){
                        $q->whereIn('cost_id', [11]); // Delivery
                    });
                })->orWhere(function($q2)
                {
                    $q2->where('system_id', '!=', 3) // Not Ozon
                    ->whereDoesntHave('costs', function ($q){
                        $q->whereIn('cost_id', [11, 14]); // Delivery
                    });
                });
            });
        }


        if($Filter->noneIncomeDelivery)
        {
            $Sales->whereDoesntHave('incomes', function ($q) use ($Filter)
            {
                $q->where('income_id', '=', 1); // Delivery
            });
        }

        if($Filter->noneCarrier)
        {
            $Sales->whereHas('packs', function ($q) use ($Filter)
            {
                $q->whereNull('carrier_id');
            });
        }

        if($Filter->noneDepartureDate)
        {
            $Sales->whereHas('packs', function ($q) use ($Filter)
            {
                $q->whereNull('departure_date');
            });
        }

        if($Filter->noneIncomes)
        {
            $Sales->whereDoesntHave('incomes');
        }

        if($Filter->zeroCostDelivery)
        {
            $Sales->whereHas('costs', function ($q) use ($Filter) {
                $q->where([['cost_id', '=', 11], ['value', '=', 0]]); // Delivery
            });
        }


        if($Filter->zeroIncomeDelivery)
        {
            $Sales->whereHas('incomes', function ($q) use ($Filter)
            {
                $q->where([['income_id', '=', 1], ['value', '=', 0]]); // Delivery
            });
        }


        if(isset($Filter->hasWriteOff) and $Filter->hasWriteOff)
        {
            $Sales->where(function($q)
            {
                $q
                    ->where('products_written_off', 1)
                    ->orWhere('sent_without_write_off', 1);
            });
            //$Sales->where('products_written_off', 1);
        }
        if(isset($Filter->doesntHaveWriteOff) and $Filter->doesntHaveWriteOff)
        {
            $Sales->where(function($q)
            {
                $q
                    ->where('products_written_off', 0)
                    ->where('sent_without_write_off', 0);
            });
            ////$Sales->where('products_written_off', 0);
        }

        if(isset($Filter->sentWithoutWriteOff) and $Filter->sentWithoutWriteOff)
        {
            $Sales->where('sent_without_write_off', 1);
        }




        if((isset($Filter->hasFileIncome) and $Filter->hasFileIncome) or (isset($Filter->hasFileCost) and $Filter->hasFileCost))
        {

            $Sales->whereHas('financesFromFile', function($qFinancesFromFile) use ($Filter)
            {

                if($Filter->hasFileIncome)
                {

                    if($Filter->hasFileIncome === '1')
                    {
                        $qFinancesFromFile->whereHas('service', function($q)
                        {
                            $q->whereNull('cost_id');
                        });
                    }else if($Filter->hasFileIncome === '2')
                    {
                        $qFinancesFromFile->whereDoesntHave('service', function($q)
                        {
                            $q->whereNull('cost_id');
                        });
                    }
                }

                if($Filter->hasFileCost)
                {
                    if($Filter->hasFileCost === '1')
                    {
                        $qFinancesFromFile->whereHas('service', function($q)
                        {
                            $q->whereNotNull('cost_id');
                        });
                    }else if($Filter->hasFileCost === '2')
                    {
                        $qFinancesFromFile->whereDoesntHave('service', function($q)
                        {
                            $q->whereNotNull('cost_id');
                        });
                    }
                }
            });
        }
    }

    public static function getFilter($request, $UserOption = false)
    {
        if (!$UserOption) $UserOption = auth()->user()->options;
        return $UserOption->sales_filter ?? false;
    }

    public static function setFilter($request, $UserOption = false)
    {
        if (!$UserOption) $UserOption = auth()->user()->options;

        $Filter = new \stdClass();

        $Filter->sp_sale = (int) $request->input('sp_sale');
        $Filter->self_redemption = (int) $request->input('self_redemption');

        $Filter->ritmz_number = $request->input('ritmz_number', NULL);
        $Filter->numberKey = $request->input('numberKey', NULL);
        $Filter->numberKeyEqual = $request->input('numberKeyEqual', NULL) ? true : false;

        $Filter->dateOrderFrom = $request->input('dateOrderFrom', NULL);
        $Filter->dateOrderTo = $request->input('dateOrderTo', NULL);

        $Filter->dateSaleFrom = $request->input('dateSaleFrom', NULL);
        $Filter->dateSaleTo = $request->input('dateSaleTo', NULL);

        $Filter->packDepartureDateFrom = $request->input('packDepartureDateFrom', NULL);
        $Filter->packDepartureDateTo = $request->input('packDepartureDateTo', NULL);

        $Filter->upon_receipt = $request->input('upon_receipt', NULL);

        $Filter->ordersTypeShopIds = $request->input('orders_type_shop_ids', []);
        $Filter->carriersIds = $request->input('carriersIds', []);

        $Filter->nonePurchasePrice = $request->input('nonePurchasePrice', 0);
        $Filter->noneCostDelivery = $request->input('noneCostDelivery', 0);
        $Filter->noneIncomeDelivery = $request->input('noneIncomeDelivery', 0);
        $Filter->zeroCostDelivery = $request->input('zeroCostDelivery', 0);
        $Filter->zeroIncomeDelivery = $request->input('zeroIncomeDelivery', 0);

        $Filter->hasWriteOff = $request->input('hasWriteOff', 0);
        $Filter->doesntHaveWriteOff = $request->input('doesntHaveWriteOff', 0);
        $Filter->sentWithoutWriteOff = $request->input('sentWithoutWriteOff', 0);

        $Filter->noneCarrier = $request->input('noneCarrier', 0);
        $Filter->noneDepartureDate = $request->input('noneDepartureDate', 0);
        $Filter->noneIncomes = $request->input('noneIncomes', 0);

        $Filter->showSalesWithProductsStatusesIds = $request->input('showSalesWithProductsStatusesIds', []);
        $Filter->hideSalesWithProductsStatusesIds = $request->input('hideSalesWithProductsStatusesIds', []);

        $Filter->hasFileIncome = $request->input('hasFileIncome', NULL);
        $Filter->hasFileCost = $request->input('hasFileCost', NULL);

        $UserOption->sales_per_page = $request->input('numberPerPage', 50);
        $UserOption->sales_last_page = 1;
        $UserOption->sales_filter = $Filter;
        $UserOption->save();

        return $Filter;
    }


    /* /From user side */


    public static function setSalesStatus($salesIds, $statusId)
    {
        foreach ($salesIds as $saleId)
        {
            if($Sale = self::getSale($saleId))
            {
                $salesProducts = SalesProduct::where([
                    ['sale_id', '=', $saleId]
                ])->whereIn('status_id', [1, 8]) // ONLY FOR NEW/DELIVERED ITEMS
                ->get();

                foreach ($salesProducts as $saleProduct)
                {
                    $saleProduct->status_id = $statusId;
                    if($saleProduct->save())
                    {

                    }else{
                        return ['error' => 'Ошибка обновления статуса у товара ' . $saleProduct->id . ' продажи ' . $saleId];
                    };
                }
            }else{
                return ['error' => 'Не найдена продажа ' . $saleId];
            }
        }

        return ['error' => false];
    }

    public static function setCommissionObjectValues(&$Commission, $commissionPercent, $SaleProduct)
    {
        $percent = $commissionPercent?:8.1; // default set commission = 4.1 for ozon from 2022-10-01 is 8.1

        if($commissionPercent === 0) // if commission not found earlier
        {
            $ProductCommission = Sales::getRecalculatedSaleProductCommission($SaleProduct);
            if($ProductCommission or isset($ProductCommission->commission_percent) or $ProductCommission->commission_percent)
            {
                $percent = $ProductCommission->commission_percent;
            }
        }

        $Commission->commission_percent = round((float) ($percent), 2);
        $Commission->commission_value = $SaleProduct->product_price * $Commission->commission_percent / 100;
        $Commission->commission_value = round($Commission->commission_value, 2);
    }


    public static $OzonSTVAPI = false;
    public static $OzonMSKAPI = false;
    public static $OzonSTVAPI2 = false;

    public static function getShopCommission(SalesProduct $SalesProduct): \stdClass
    {
        $Commission = new \stdClass();
        $Commission->commission_value = 0;
        $Commission->commission_percent = 0;

        if($SalesProduct->product and $SalesProduct->sale and $SalesProduct->sale->typeShop)
        {
            switch($SalesProduct->sale->typeShop->parent_shop_id?:$SalesProduct->sale->type_shop_id)
            {
                case 1:   // OzonSTV
                case 74:  // OzonStvFBO
                    if(!self::$OzonSTVAPI) self::$OzonSTVAPI = new OzonApi('Stavropol');
                    $OzonPrice = self::$OzonSTVAPI->getPriceV4($SalesProduct->product->sku);
                    self::setCommissionObjectValues($Commission, ($OzonPrice->commissions->sales_percent??0), $SalesProduct);
                    break;
                case 80:
                    self::setCommissionObjectValues($Commission, 16, $SalesProduct); // default commission
                    break;
                case 2: // OzonMSK
                    if(!self::$OzonMSKAPI) self::$OzonMSKAPI = new OzonApi('Moscow');
                    $OzonPrice = self::$OzonMSKAPI->getPriceV4($SalesProduct->product->sku);
                    self::setCommissionObjectValues($Commission, ($OzonPrice->commissions->sales_percent??0), $SalesProduct);
                    break;
                case 10001:
                        if(!self::$OzonSTVAPI2) self::$OzonSTVAPI2 = new OzonApi2('Stavropol');
                        $OzonPrice = self::$OzonSTVAPI2->getPriceV4($SalesProduct->product->sku);
                        self::setCommissionObjectValues($Commission, ($OzonPrice->commissions->sales_percent??0), $SalesProduct);
                    break;
                case 10002:
                        if(!self::$OzonSTVAPI2) self::$OzonSTVAPI2 = new OzonApi2('Moscow');
                        $OzonPrice = self::$OzonSTVAPI2->getPriceV4($SalesProduct->product->sku);
                        self::setCommissionObjectValues($Commission, ($OzonPrice->commissions->sales_percent??0), $SalesProduct);
                    break;
            }
        }

        return $Commission;
    }

    public static function recalculateCommission(Sale $Sale)
    {
        if(!empty($Sale->system_id))
        {
            foreach($Sale->products as $SalesProduct)
            {
                self::recalculateSaleProductCommission($SalesProduct);
            }
        }
    }

    public static function recalculateSaleProductCommission(SalesProduct $SalesProduct)
    {
        if($SalesProduct->sale and ($SalesProduct->sale->system_id === 3))
        {
            $Commission = self::getShopCommission($SalesProduct);

            $SalesProduct->commission_value = $Commission->commission_value;
            $SalesProduct->commission_percent = $Commission->commission_percent;
            $SalesProduct->commission_min_value = 0;
            $SalesProduct->commission_deduction_value = 0;
            $SalesProduct->save();

        }else // default super commission table check by period
        {
            $ProductCommission = self::getRecalculatedSaleProductCommission($SalesProduct);
            if($ProductCommission)
            {
                $SalesProduct->commission_value = $ProductCommission->commission_value;
                $SalesProduct->commission_percent = $ProductCommission->commission_percent;
                $SalesProduct->commission_min_value = $ProductCommission->commission_min_value;
                $SalesProduct->commission_deduction_value = $ProductCommission->commission_deduction_value;
                $SalesProduct->save();
            }
        }
    }

    public static function getRecalculatedSaleProductCommission(SalesProduct $SalesProduct)
    {
        if($Sale = $SalesProduct->sale)
        {
            if($Order = $Sale->order)
            {
                $orderDate = $Order->info->order_date_create;
            }else{
                $orderDate = Carbon::parse($Sale->date_sale, 'Europe/Moscow')->setTimezone('UTC')->toDateTimeString();
            }
        }else{
            return false;
        }

        $NewCommission = new \stdClass();

        $SystemsCommission = Products::getCommission($SalesProduct->product, $Sale->system_id, $orderDate);
        if (!$SystemsCommission) return false;

        $product_price = $SalesProduct->product_price;


        $commission_value = $product_price * $SystemsCommission->value_percent / 100;
        $commission_value = round($commission_value, 2); // ??

        $NewCommission->commission_value = $commission_value;
        $NewCommission->commission_percent = $SystemsCommission->value_percent;

        $NewCommission->commission_min_value = ($commission_value < $SystemsCommission->value_min) ? $SystemsCommission->value_min : 0;

        if ($SalesProduct->status->commission_deduction) {
            $commission_deduction_value = ($NewCommission->commission_min_value ? $NewCommission->commission_min_value : $commission_value) * $SystemsCommission->deduction_percent / 100;
            $commission_deduction_value = ($commission_deduction_value < $SystemsCommission->deduction_min) ? $SystemsCommission->deduction_min : $commission_deduction_value;
            $commission_deduction_value = intval(($commission_deduction_value) * 100) / 100;

            $NewCommission->commission_deduction_value = $commission_deduction_value;
        } else {
            $NewCommission->commission_deduction_value = 0;
        }

        return $NewCommission;
    }

    public static function getCommission($Sale)
    {
        $commission = 0;

        $commissionedProducts = $Sale->products->filter(function ($SaleProduct)
        {
            return $SaleProduct->status->commission_calculation or $SaleProduct->status->commission_deduction;
        });

        foreach($commissionedProducts as $SaleProduct)
        {
            if($SaleProduct->status->commission_calculation)
            {
                $commission += (($SaleProduct->commission_min_value > $SaleProduct->commission_value) ? $SaleProduct->commission_min_value : $SaleProduct->commission_value) * $SaleProduct->product_quantity;
            }

            if($SaleProduct->status->commission_deduction_value)
            {
                $commission += $SaleProduct->commission_deduction_value * $SaleProduct->product_quantity;
            }
        }

        return $commission;
    }

    public static function getDeliveryPrice(Sale $Sale)
    {
        $deliveryPrice = 0;
        if (isset($Sale->order) and !empty($Sale->system_id)) {
            $orderDate = Carbon::parse($Sale->order->info->order_date_create, 'UTC')->setTimezone('Europe/Moscow')->format('Y-m-d');

            $SystemsDeliveryPrice = SystemsDeliveryPrice::where('system_id', $Sale->system_id)
                ->where(function ($query) use ($orderDate) {
                    $query->where(function ($query2) use ($orderDate) {
                        $query2->where([['used_since', '<=', $orderDate]])->orWhereNull('used_since');
                    })
                        ->where(function ($query2) use ($orderDate) {
                            $query2->where([['used_to', '>=', $orderDate]])->orWhereNull('used_to');
                        });
                })->orderBy('id', 'DESC')->first();

            if ($SystemsDeliveryPrice) {
                $deliveryPrice = $SystemsDeliveryPrice->price;
            }
        }

        return $deliveryPrice;
    }

    public static function recalculateDelivery(Sale $Sale)
    {
        $deliveryCostId = 14;
        $deliveryCostReturnId = 15;

        if (!$deliveryPrice = self::getDeliveryPrice($Sale)) return false;

        $countProducts = (int)$Sale->products()->whereHas('status', function ($q) {
            $q->where('delivery_counting', '!=', 0);
        })->sum('product_quantity');
        $totalDeliveryPrice = $deliveryPrice * $countProducts;

        $countReturnedProducts = (int)$Sale->products()->whereHas('status', function ($q) {
            $q->where('delivery_counting', -1);
        })->sum('product_quantity');
        $totalDeliveryPriceReturn = $deliveryPrice * $countReturnedProducts;

        if ($totalDeliveryPrice > 0) {
            $deliveryCosts = $Sale->costs()->where('cost_id', $deliveryCostId)->get();
            $DeliveryCost = $deliveryCosts->first();
            if (!$DeliveryCost) {
                $DeliveryCost = new SalesCost;
                $DeliveryCost->sale_id = $Sale->id;
                $DeliveryCost->cost_id = $deliveryCostId;
            }
            $DeliveryCost->value_type_id = 1;
            $DeliveryCost->flow_type_id = 1;
            $DeliveryCost->value = $totalDeliveryPrice;
            $DeliveryCost->comment = 'Авто-расчёт';
            $DeliveryCost->save();

            if (count($deliveryCosts) > 1) {
                $Sale->costs()->where([
                    ['id', '!=', $DeliveryCost->id],
                    ['cost_id', '=', $deliveryCostId]
                ])->get()->each(function ($SalesCost) {
                    $SalesCost->delete();
                });
            }
        } else {
            $Sale->costs()->where([
                ['cost_id', '=', $deliveryCostId]
            ])->get()->each(function ($SalesCost) {
                $SalesCost->delete();
            });
        };

        if ($totalDeliveryPriceReturn > 0) {
            $deliveryReturnCosts = $Sale->costs()->where('cost_id', $deliveryCostReturnId)->get();
            $DeliveryReturnCost = $deliveryReturnCosts->first();
            if (!$DeliveryReturnCost) {
                $DeliveryReturnCost = new SalesCost;
                $DeliveryReturnCost->sale_id = $Sale->id;
                $DeliveryReturnCost->cost_id = $deliveryCostReturnId;
            }
            $DeliveryReturnCost->value_type_id = 1;
            $DeliveryReturnCost->flow_type_id = 1;
            $DeliveryReturnCost->value = $totalDeliveryPriceReturn;
            $DeliveryReturnCost->comment = 'Авто-расчёт';
            $DeliveryReturnCost->save();

            if (count($deliveryReturnCosts) > 1) {
                $Sale->costs()->where([
                    ['id', '!=', $DeliveryReturnCost->id],
                    ['cost_id', '=', $deliveryCostReturnId]
                ])->get()->each(function ($SalesCost) {
                    $SalesCost->delete();
                });
            }
        } else {
            $Sale->costs()->where([
                ['cost_id', '=', $deliveryCostReturnId]
            ])->get()->each(function ($SalesCost) {
                $SalesCost->delete();
            });
        }

        return true;
    }

    public static function translateStatusDelivered(Sale $Sale)
    {
        foreach ($Sale->products as $SaleProduct) {
            if ($SaleProduct->status->delivery_status === 0) {
                $SaleProduct->status_id = 8; // sale delivered
                $SaleProduct->save();
            }
        }
    }

    public static function translateStatus(Sale $Sale, $salesProductsStatusId)
    {
        foreach($Sale->products as $SaleProduct)
        {
            if($SaleProduct->status->delivery_status === 0)
            {
                $SaleProduct->status_id = $salesProductsStatusId;
                $SaleProduct->save();
            }
        }
    }

    public static function getIntermediaryCommission($Sale)
    {
        $intermediaryCommission = 0;

        $costs = $Sale->costs->where('cost_id', 9);
        foreach ($costs as $Cost) {
            $intermediaryCommission += Sales::getIncomeOrCostValue($Cost, $Sale);
        }

        return $intermediaryCommission;
    }

    public static function getAffiliateCommission($Sale)
    {
        $affiliateCommission = 0;

        $costs = $Sale->costs->where('cost_id', 10);
        foreach ($costs as $Cost) {
            $affiliateCommission += Sales::getIncomeOrCostValue($Cost, $Sale);
        }

        return $affiliateCommission;
    }

    public static function getAccountsReceivable(Sale $Sale)
    {
        $accountsReceivable = 0;

        switch ($Sale->system_id) {
            case 1:
            case 2: // InSales и Тиу: тут просто, берём цифру из продажи, которую ты уже сделал "Итого к оплате"
                // totalPrice - totalDiscount + totalIncomes - prepaymentMade
                $accountsReceivable = $Sale->TotalPayable;
                break;
            case 3: // Озоны: Товар - Комиссия - Доставка = искомая величина
                $accountsReceivable = $Sale->TotalPrice - $Sale->Commission - $Sale->SystemDeliveryRub - $Sale->SystemDeliveryReturnRub;
                break;
            case 4: //  GOODs: Товар + доп. доход - комиссия = искомая величина
                $accountsReceivable = $Sale->TotalPrice + $Sale->IncomesValue - $Sale->Commission;
                break;
            //case 6: // Tmall (+Али): Товар + Доп.Доход - комиссия площадки - комиссия посредника - комиссия аффилиата
            //        $accountsReceivable = $Sale->TotalSalePrice + $Sale->IncomesValue - $Sale->Commission - self::getIntermediaryCommission($Sale) - self::getAffiliateCommission($Sale);
            case 6: // Tmall (+Али): Товар + Доп.Доход - комиссия площадки - комиссия аффилиата
                $accountsReceivable = $Sale->TotalPrice + $Sale->IncomesValue - $Sale->Commission - self::getAffiliateCommission($Sale);
                break;
            case 69: // KupiVip - это Цена продажи
                $accountsReceivable = $Sale->TotalPrice;
                break;
        }
        return $accountsReceivable;
    }

    public static function updateProductsPurchasePrice($saleProducts)
    {
        foreach($saleProducts as $SaleProduct)
        {
            $SaleProduct->purchase_price = $SaleProduct->product->purchase_average_price;
            $SaleProduct->save();
        }
    }

    public static function checkProductsEquals(Sale $Sale, $products)
    {
        $errors = false;
        foreach($Sale->products as $SaleProduct)
        {
            $found = false;
            foreach($products as $Product)
            {
                if($SaleProduct->product->sku === $Product->sku
                and $SaleProduct->product_quantity === $Product->quantity)
                {
                    $Product->checked = true;
                    $found = true;
                }
            }

            if(!$found) return "Не соответствие продажи! Продукт $Product->sku не найден в списке списания. Возможно, продажа не была сохранена или уже изменилась.";
        }

        foreach($products as $Product)
        {
            if(!isset($Product->checked)) return "Не соответствие продажи! Продукт $Product->sku не найден в продаже. Возможно, продажа не была сохранена или уже изменилась.";
        }

        return $errors;
    }

    public static function updateSaleCommission($Order, $SystemOrder)
    {
        try{
            switch($Order->type_shop_id)
            {
                case 67: //Беру (ЯМ-плейс) FBY
                        //self::updateYandexFBYCommissions($Order, $SystemOrder);
                    break;
            }
        }catch(\Exception $e){
            // do task when error
            echo $e->getMessage();   // insert query
            return false;
        }
    }

    public static function updateIncomeByDeliveryPrice($Sale, $newValue, $oldValue = false)
    {
        if($oldValue)
        {
            $q['value'] = $oldValue;
            $SaleIncome = $Sale->incomes()->firstOrNew([
                'income_id' => 1,
                'value_type_id' => 1,
                'flow_type_id' => 1,
                'value' => $oldValue,
            ]);
        }else{
            $SaleIncome = new SalesIncome;
            $SaleIncome->sale_id = $Sale->id;
            $SaleIncome->income_id = 1;
            $SaleIncome->value_type_id = 1;
            $SaleIncome->flow_type_id = 1;
        }

        $SaleIncome->value = $newValue;
        $SaleIncome->save();
    }


    public static function getSaleTotalsWithAPIAndFile($Sale)
    {
        $tableTotals = [];

        $saleProducts = $Sale->products;
        $financesFromFile = SalesFinancesFromFile::whereHas('service', function($q){
            $q->whereNull('cost_id');
        })->where([
            ['sale_id', $Sale->id],
            //['shop_id', $Sale->type_shop_id], // WTF??
        ])->get();

        foreach($saleProducts as $SaleProduct)
        {
            $TableTotal = new \stdClass();
            $TableTotal->saleProduct = $SaleProduct;
            $TableTotal->financesFromFiles = [];
            $TableTotal->totalPrice = 0;
            $TableTotal->wbTotalIncome = 0;

            foreach($financesFromFile as $fKey => $FinancesFromFile)
            {
                if($SaleProduct->product_id === $FinancesFromFile->product_id)
                {
                    $TableTotal->financesFromFiles[] = $FinancesFromFile;
                    $TableTotal->totalPrice += $FinancesFromFile->price;
                    $TableTotal->wbTotalIncome += $FinancesFromFile->wb_income;
                    unset($financesFromFile[$fKey]);
                }
            }

            $tableTotals[] = $TableTotal;
        }

        foreach($financesFromFile as $FinancesFromFile)
        {
            $TableTotal = new \stdClass();
            $TableTotal->financesFromFiles = [];
            $TableTotal->financesFromFiles[] = $FinancesFromFile;
            $tableTotals[] = $TableTotal;
        }

        foreach($Sale->incomes as $SaleIncome)
        {
            $TableTotal = new \stdClass();
            $TableTotal->financesFromFiles = [];
            $TableTotal->saleProduct = new \stdClass();
            $TableTotal->saleProduct->TotalPrice = $SaleIncome->TotalValue;
            $TableTotal->saleProduct->product = new \stdClass();
            $TableTotal->saleProduct->product->sku = $SaleIncome->income->name;
            $tableTotals[] = $TableTotal;
        }

        return $tableTotals;
    }

    public static function getIncomeOrCostFormula($SaleCost, $Sale)
    {
        $CostsDefault = CostsDefault::where(function($query) use ($Sale, $SaleCost)
        {
            $query->whereNull('orders_type_shop_id')->orWhere('orders_type_shop_id', Shops::getShopIdRules($Sale->type_shop_id));
        })->where(function($query) use ($Sale){
            $query->where('value_type_id', 3);
        })->where(function($query) use ($Sale){
            $query->whereNull('upon_receipt')->orWhere('upon_receipt', $Sale->UponReceipt);
        })->whereHas('cost', function($q) use ($SaleCost)
        {
            $q
                ->where('id', $SaleCost->cost_id)
                ->where('auto_add', 1)
                ->where('state', 1);
        })
        ->where(function($query) use ($Sale)
        {
            $query
                ->where(function($query) use ($Sale)
                {
                    $query->whereNull('datetime_from')->orWhere('datetime_from', '<=', $Sale->date_sale);
                })->where(function($query) use ($Sale)
                {
                    $query->whereNull('datetime_to')->orWhere('datetime_to', '>=', $Sale->date_sale);
                });
        })
        ->first();

        if($CostsDefault)
        {
            return Costs::autoCostsGetValueForSale($Sale, $CostsDefault, false)->formulaText;
        }

        return '';
    }

    public static function getSaleCostsWithAPIAndFile($Sale, $test = false)
    {
        $tableCosts = [];

        $usedCosts =  Sales::getUsedCosts($Sale);

        $salesFinancesDefaultListServices = $Sale->shop->salesFinancesDefaultListServices;
        if(count($salesFinancesDefaultListServices) > 0)
        {
            $usedCostsIds =  $usedCosts->pluck('cost_id')->toArray();

            foreach($salesFinancesDefaultListServices as $SalesFinancesDefaultListService)
            {
                if(!$SalesFinancesDefaultListService->cost_id) continue;
                $TableCost = new \stdClass();
                $TableCost->cost_id = $SalesFinancesDefaultListService->cost_id;
                $TableCost->service_id = $SalesFinancesDefaultListService->service_id;
                $TableCost->name = $SalesFinancesDefaultListService->cost->name;
                $TableCost->costName = $SalesFinancesDefaultListService->cost->name;
                $TableCost->serviceName = $SalesFinancesDefaultListService->service->name;

                // cost
                if($SaleCost = $Sale->costs->where('cost_id', $TableCost->cost_id)->first())
                {
                    $TableCost->calcPrice = round(self::getIncomeOrCostValue($SaleCost, $Sale), 2);
                    $TableCost->calcFormula = self::getIncomeOrCostFormula($SaleCost, $Sale);
                }

                // file
                $financesFromFile = SalesFinancesFromFile::whereHas('service', function($q) use ($TableCost)
                {
                    $q->where('cost_id', $TableCost->cost_id);
                })->where([
                    ['sale_id', $Sale->id],
                    //['shop_id', $Sale->TypeShop->main_shop_id],
                ])->get();

                //if($test) dd($financesFromFile);

                foreach($financesFromFile as $FinancesFromFile)
                {
                    if(!isset($TableCost->files)) $TableCost->files = [];
                    if(!array_key_exists($FinancesFromFile->file_id, $TableCost->files))
                    {
                        $TableCost->files[$FinancesFromFile->file_id] = $FinancesFromFile->file;
                    }

                    if(!isset($TableCost->files[$FinancesFromFile->file_id]->filePrice))
                        $TableCost->files[$FinancesFromFile->file_id]->filePrice = 0;
                    $TableCost->files[$FinancesFromFile->file_id]->filePrice += $FinancesFromFile->price;

                    if(!isset($TableCost->filePrice)) $TableCost->filePrice = 0;
                    $TableCost->filePrice += $FinancesFromFile->price;

                    $TableCost->FinancesFromFile = $FinancesFromFile;
                }

                // API
                $financesFromAPIs = SalesFinancesFromAPI::whereHas('service', function($q) use ($TableCost)
                {
                    $q->where('cost_id', $TableCost->cost_id);
                })->where([
                    ['sale_id', $Sale->id],
                    //['shop_id', $Sale->type_shop_id],
                    ['shop_id', $Sale->TypeShop->main_shop_id],
                ])->get();

                //if($test) dd(Shops::getShopIdRules($Sale->type_shop_id));

                foreach($financesFromAPIs as $FinancesFromAPI)
                {
                    if(!isset($TableCost->apiPrice)) $TableCost->apiPrice = 0;
                    $TableCost->apiPrice += $FinancesFromAPI->price;
                }

                $tableCosts[] = $TableCost;
                unset($TableCost);
            }
        }else{
            foreach($usedCosts as $SaleCost)
            {
                $TableCost = new \stdClass();
                $TableCost->cost_id = $SaleCost->cost_id;
                $TableCost->name = $SaleCost->cost->name;
                $TableCost->calcPrice = round(self::getIncomeOrCostValue($SaleCost, $Sale), 2);
                $TableCost->calcFormula = self::getIncomeOrCostFormula($SaleCost, $Sale);
                $tableCosts[] = $TableCost;

                unset($TableCost);
            }

            $financesFromFile = SalesFinancesFromFile::whereHas('service', function($q){
                $q->whereNotNull('cost_id');
            })->where([
                ['sale_id', $Sale->id],
                //['shop_id', $Sale->type_shop_id],
                //['shop_id', $Sale->TypeShop->main_shop_id],
            ])->get();

            foreach($financesFromFile as $FinancesFromFile)
            {
                foreach($tableCosts as $tKey => $TableCost)
                {
                    if($TableCost->cost_id === $FinancesFromFile->service->cost_id)
                    {
                        $TableCost2 = $TableCost;
                        break;
                    }
                }

                $needToAdd = false;
                if(!isset($TableCost2))
                {
                    $needToAdd = true;
                    $TableCost2 = new \stdClass();
                    $TableCost2->name = $FinancesFromFile->service->name;
                    $TableCost2->cost_id = $FinancesFromFile->service->cost_id;
                }

                if(!isset($TableCost2->files)) $TableCost2->files = [];
                if(!array_key_exists($FinancesFromFile->file_id, $TableCost2->files))
                {
                    $TableCost2->files[$FinancesFromFile->file_id] = $FinancesFromFile->file;
                }

                if(!isset($TableCost2->files[$FinancesFromFile->file_id]->filePrice))
                    $TableCost2->files[$FinancesFromFile->file_id]->filePrice = 0;
                $TableCost2->files[$FinancesFromFile->file_id]->filePrice += $FinancesFromFile->price;

                if(!isset($TableCost2->fileFinance))
                    $TableCost2->fileFinance = [];

                $TableCost2->fileFinance[] = $FinancesFromFile;




                if(!isset($TableCost2->filePrice)) $TableCost2->filePrice = 0;
                $TableCost2->filePrice += $FinancesFromFile->price;

                $TableCost2->FinancesFromFile = $FinancesFromFile;

                if($needToAdd)
                {
                    $tableCosts[] = $TableCost2;
                }else{
                    $tableCosts[$tKey] = $TableCost2;
                }

                unset($TableCost2);
            }

            $financesFromAPIs = SalesFinancesFromAPI::whereHas('service', function($q){
                $q->whereNotNull('cost_id');
            })->where([
                ['sale_id', $Sale->id],
            ])->get();

            foreach($financesFromAPIs as $FinancesFromAPI)
            {
                foreach($tableCosts as $tKey => $TableCost)
                {
                    if($TableCost->cost_id === $FinancesFromAPI->service->cost_id)
                    {
                        $TableCost3 = $TableCost;
                        break;
                    }
                }

                $needToAdd = false;
                if(!isset($TableCost3))
                {
                    $needToAdd = true;
                    $TableCost3 = new \stdClass();
                    $TableCost3->name = $FinancesFromAPI->service->name;
                    $TableCost3->cost_id = $FinancesFromAPI->service->cost_id;
                }

                if(!isset($TableCost3->apiPrice)) $TableCost3->apiPrice = 0;
                $TableCost3->apiPrice += $FinancesFromAPI->price;

                if($needToAdd)
                {
                    $tableCosts[] = $TableCost3;
                }else{
                    $tableCosts[$tKey] = $TableCost3;
                }

                unset($TableCost3);
            }
        }

        // group by file service sum -> only view
        foreach($tableCosts as $TableCost)
        {
            if(isset($TableCost->fileFinance))
            {
                $TableCost->fileFinance = collect($TableCost->fileFinance);
                $TableCost->services = [];
                foreach($TableCost->fileFinance->groupBy('service_id') as $serviceId => $fileFinances)
                {
                    if($SalesFinancesService = SalesFinancesService::where('id', $serviceId)->first())
                    {
                        $SalesFinancesService->fileFinancesSum = $fileFinances->sum('price');
                        $TableCost->services[] = $SalesFinancesService;
                    }
                }

            }
        }


        return $tableCosts;
    }

    public static function getReturnPeriodDays($Sale)
    {
        $returnPeriodDaysType = false;
        $returnPeriodDays = 0;
        $saleProducts = $Sale->products->whereNotIn('status_id', [2, 12]);
        foreach($saleProducts as $SaleProduct)
        {
            $StatusHistory1 = false;
            $StatusHistory2 = false;
            $count = count($SaleProduct->statusHistories);
            foreach($SaleProduct->statusHistories as $key => $StatusHistory)
            {
                if(!$StatusHistory1 and in_array($StatusHistory->new_status_id, [5, 10]))
                {

                    $StatusHistory1 = $StatusHistory;
                }

                if(
                    $StatusHistory1
                    and !$StatusHistory2
                    //and ($StatusHistory->old_status_id === $StatusHistory1->new_status_id)
                    and !in_array($StatusHistory->new_status_id, [5, 10, 12])
                )
                {
                    $StatusHistory2 = $StatusHistory;
                }

                if($StatusHistory1 and $StatusHistory2)
                {
                    $DateStatus = Carbon::parse($StatusHistory1->created_at);
                    $DateStatus2 = Carbon::parse($StatusHistory2->created_at);
                    $diffDays = $DateStatus->diffInDays($DateStatus2);

                    $returnPeriodDays = ($returnPeriodDays > $diffDays)?$returnPeriodDays:$diffDays;
                    $returnPeriodDaysType = 1;
                }

                if(($key === ($count-1)) and $StatusHistory1 and !$StatusHistory2)
                {
                    $DateStatus = Carbon::parse($StatusHistory1->created_at);
                    $diffDays = $DateStatus->diffInDays(Carbon::now()->endOfDay());

                    $returnPeriodDays = ($returnPeriodDays > $diffDays)?$returnPeriodDays:$diffDays;
                    $returnPeriodDaysType = 2;
                }
            }
        }

        $res = new \stdClass();
        $res->days = $returnPeriodDays;
        $res->type = $returnPeriodDaysType;
        return $res;
    }

    public static function getDeliveryPeriodDays($Sale)
    {
        $deliveryPeriodDaysType = false;
        $deliveryPeriodDays = 0;
        $saleProducts = $Sale->products->whereNotIn('status_id', [12]);

        $SaleDepartureDate = !empty($Sale->packs[0]->departure_date)?Carbon::parse($Sale->packs[0]->departure_date):Carbon::parse($Sale->created_at);

        foreach($saleProducts as $SaleProduct)
        {
            $StatusHistory1 = false;
            $count = count($SaleProduct->statusHistories);
            foreach($SaleProduct->statusHistories as $key => $StatusHistory)
            {
                /*
                if(!$StatusHistory1 and ($StatusHistory->new_status_id === 8))
                {
                    $DateStatus = Carbon::parse($StatusHistory->created_at);
                    $diffDays = $SaleDepartureDate->diffInDays($DateStatus);
                    $deliveryPeriodDays = ($deliveryPeriodDays > $diffDays)?$deliveryPeriodDays:$diffDays;
                    $deliveryPeriodDaysType = 1;
                    break;
                }
                */
                if(!$StatusHistory1 and ($StatusHistory->old_status_id === 1))
                {
                    $DateStatus = Carbon::parse($StatusHistory->created_at);
                    $diffDays = $SaleDepartureDate->diffInDays($DateStatus);
                    $deliveryPeriodDays = ($deliveryPeriodDays > $diffDays)?$deliveryPeriodDays:$diffDays;
                    $deliveryPeriodDaysType = 1;
                    break;
                }


                if(($key === ($count-1)) and !$StatusHistory1)
                {
                    $diffDays = $SaleDepartureDate->diffInDays(Carbon::now()->endOfDay());
                    $deliveryPeriodDays = ($deliveryPeriodDays > $diffDays)?$deliveryPeriodDays:$diffDays;
                    $deliveryPeriodDaysType = 2;
                }
            }
        }

        $res = new \stdClass();
        $res->days = $deliveryPeriodDays;
        $res->type = $deliveryPeriodDaysType;
        return $res;
    }

    public static function updateProductsWrittenOffAttribute($saleId)
    {
        if($Sale = Sale::where('id', $saleId)->first())
        {
            $Sale->products_written_off = $Sale->HasPseudoWriteOff;
            $Sale->save();
        }
    }

    public static function beforeSave($Sale)
    {
        if($Sale->id) // only if not create new
            Sales::updateData($Sale);
    }

    public static function updateData($Sale)
    {
        Costs::updateCostsForSale($Sale); // any updating = update AUTO_UPDATE costs - only before save
        $Sale->margin_value = number_format(Sales::getMarginValue($Sale), 2, '.', '');
    }

    public static function getUsedCosts(Sale $Sale)
    {
        $productsStatusesIds = $Sale->products->pluck('status_id')->toArray();

        $calcCostsStatusesIds = SystemsMarginCalcByStatus::where([
            ['type_shop_id', $Sale->type_shop_id],
            ['costs', 1],
        ])->pluck('sales_products_status_id')->toArray();


        $usedCostsIds = SystemsMarginCalcCostsByStatus::where([
            ['type_shop_id', $Sale->type_shop_id],
            ['calc', 1],
        ])
            ->whereIn('sales_products_status_id', $productsStatusesIds)
            ->whereIn('sales_products_status_id', $calcCostsStatusesIds)
            ->pluck('cost_id')->toArray();

        $usedCosts = SalesCost::where([
            ['sale_id', $Sale->id],
        ])->whereIn('cost_id', $usedCostsIds)->get();

        return $usedCosts;
    }

    public static function getProductsListToWriteOff($Sale)
    {
        $productsToWriteOff = [];

        if(!$shopWarehouses = Warehouses::getShopWarehouses($Sale->type_shop_id))
        {
            Notifications::new(
                'Авто-списание: ошибка!',
                "У продажи $Sale->AHref не найдены склады списания.",
                'sale_auto_movement_no_warehouses'
            );
            return false;
        }

        foreach($Sale->products as $SaleProduct)
        {
            $totalOnWarehouses = 0;
            $countProductsNeed = $SaleProduct->product_quantity;

            foreach($shopWarehouses as $ShopWarehouse)
            {
                if(
                    $WarehouseProductAmount = WarehouseProductsAmounts::get($SaleProduct->product_id, $ShopWarehouse->id)
                        and
                    ($WarehouseProductAmount->Balance > 0)
                )
                {
                    $ProductToWriteOff = new \stdClass();
                    $ProductToWriteOff->product_id = $SaleProduct->product_id;

                    $ProductToWriteOff->warehouse_id = $ShopWarehouse->id;

                    if($WarehouseProductAmount->Balance - $countProductsNeed >= 0) // enough quantity
                    {
                        $ProductToWriteOff->product_quantity = $countProductsNeed;
                        $totalOnWarehouses += $countProductsNeed;

                        $countProductsNeed -= $countProductsNeed;
                    }else
                    {
                        $ProductToWriteOff->product_quantity = $WarehouseProductAmount->Balance;
                        $totalOnWarehouses += $WarehouseProductAmount->Balance;

                        $countProductsNeed -= $WarehouseProductAmount->Balance;
                    }

                    $productsToWriteOff[] = $ProductToWriteOff;
                }

                if($countProductsNeed === 0)
                    break;
            }

            if(($countProductsNeed > 0))
            {
                if(($SaleProduct->product_quantity !== $countProductsNeed))
                {
                    Notifications::new(
                        'Авто-списание: ошибка!',
                        "У продажи $Sale->AHref попытка частичного списания из-за недостатка товара.",
                        'sale_auto_movement_part_stocks'
                    );
                }else
                {
                    Notifications::new(
                        'Авто-списание: ошибка!',
                        "У продажи $Sale->AHref нет товара на складе: {$SaleProduct->product->sku}.",
                        'sale_auto_movement_no_stocks'
                    );
                }

            }
        }

        return $productsToWriteOff;
    }

    public static function updateQuantityAfterWriteOff($productsIdsFromSale)
    {
        $productsIds = array_unique($productsIdsFromSale);
        $products = Product::whereIn('id', $productsIds)->get();
        ShopProducts::updateQuantity($products);
    }

    public static function autoMovement() // this function find sales and write off products
    {
        $sales = Sale::where([
            ['full_created', 1],
            ['auto_movement_checked', 0],
            ['created_at', '>=', Carbon::now()->subHours(5)],
        ])
            // ->with('products', 'products.product') for exclude bug, when starts 10+ thread
            ->whereIn('type_shop_id', [177, 179, 2, 10002, 5, 1, 10001]) // now only Wildberries / OzonMsk / Goods // 10002 // , 1, 10001
            ->whereDoesntHave('movements')
            ->orderBy('created_at', 'DESC')
        ->get();

        $productsIdsFromSale = [];

        foreach($sales as $Sale)
        {
            $Sale->auto_movement_checked = 1;
            $Sale->save();

            if($productsToWriteOff = self::getProductsListToWriteOff($Sale))
            {
                $saleProducts = SalesProduct::with('product')->where('sale_id', $Sale->id)->get();

                $WarehouseMovement = WarehouseMovements::new(
                    -1,
                    NULL,
                    $productsToWriteOff,
                    $Sale->order_id,
                    $Sale->id,
                    1
                );

                if($WarehouseMovement)
                {
                    Sales::updateProductsPurchasePrice($saleProducts);

                    $productsIdsFromSale[] = $Sale->products->pluck('product_id')->toArray();

                    Sales::historyAdd(
                        $Sale->id,
                        $WarehouseMovement,
                        'auto-movement-1'
                    );
                }else
                {
                    Notifications::new(
                        'Авто-списание: ошибка!',
                        "Для продажи $Sale->AHref произошла неизвестная ошибка списания.",
                        'sale_auto_movement_unknown_error'
                    );
                }
            }
        }

        if($productsIdsFromSale)
            Sales::updateQuantityAfterWriteOff($productsIdsFromSale);
    }
}


