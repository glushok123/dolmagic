<?php

namespace App\Models\Sales;

use App\Eloquent\Sales\Sale;
use App\Eloquent\Sales\SalesProduct;
use App\Eloquent\System\SystemsMarginCalcCostsByStatus;
use App\Eloquent\System\SystemsMarginCalcIncomesByStatus;
use App\Models\Model;

class SalesProducts extends Model{

    public static function getCommission(SalesProduct $SaleProduct)
    {
        $commission = 0;

        if($SaleProduct->status->commission_calculation)
        {
            $commission += (($SaleProduct->commission_min_value > $SaleProduct->commission_value)?$SaleProduct->commission_min_value:$SaleProduct->commission_value);
        }

        if($SaleProduct->status->commission_deduction_value)
        {
            $commission += $SaleProduct->commission_deduction_value;
        }

        return $commission;
    }

    public static function getIncomeOrCostValue($IncomeOrCost, $SaleProduct, $typeShopId = false)
    {
        if(!$typeShopId) $typeShopId = $SaleProduct->sale->type_shop_id;

        $total = 0;
        switch($IncomeOrCost->value_type_id)
        {
            case 1: // ₽
                switch($IncomeOrCost->flow_type_id )
                {
                    case 1: // all order
                            $TotalCountForIncomeOrCost = SalesProduct::whereHas('status', function ($q) use ($IncomeOrCost, $typeShopId){
                                if(isset($IncomeOrCost->income_id)) // is income
                                {
                                    $q->whereHas('incomesCalcs', function ($q) use ($IncomeOrCost, $typeShopId){
                                        $q->where([
                                            ['type_shop_id', $typeShopId],
                                            ['income_id', $IncomeOrCost->income_id],
                                            ['calc', 1],
                                        ]);
                                    });
                                }else if(isset($IncomeOrCost->cost_id)) // is cost
                                {
                                    $q->whereHas('costsCalcs', function ($q) use ($IncomeOrCost, $typeShopId){
                                        $q->where([
                                            ['type_shop_id', $typeShopId],
                                            ['cost_id', $IncomeOrCost->cost_id],
                                            ['calc', 1],
                                        ]);
                                    });
                                }
                            })->where('sale_id', $SaleProduct->sale_id)->sum('product_quantity');

                            if($TotalCountForIncomeOrCost > 0)
                                $total = $IncomeOrCost->value/$TotalCountForIncomeOrCost;
                        break;
                    case 2: // Each product
                            $total = $IncomeOrCost->value;
                        break;
                }
                break;
            case 2: // %
                    $total = $SaleProduct->product_price*$IncomeOrCost->value/100;
                break;
        };

        return $total;
    }

    public static function getCostsValue(SalesProduct $SaleProduct, Sale $Sale)
    {
        $SaleProductStatusCalculatedCostsIds = SystemsMarginCalcCostsByStatus::where([
            ['type_shop_id', $Sale->type_shop_id],
            ['sales_products_status_id', $SaleProduct->status_id],
            ['calc', 1],
        ])->pluck('cost_id')->toArray(); // get ids for calculating costs

        $costsValue = 0;
        foreach($Sale->costs->whereIn('cost_id', $SaleProductStatusCalculatedCostsIds) as $SaleProductStatusCost)
        {
            $costsValue
                += self::getIncomeOrCostValue($SaleProductStatusCost, $SaleProduct, $Sale->type_shop_id)
                * $SaleProduct->product_quantity;
        }
        return $costsValue;
    }

    public static function getIncomesValue(SalesProduct $SaleProduct, Sale $Sale)
    {
        $SaleProductStatusCalculatedIncomesIds = SystemsMarginCalcIncomesByStatus::where([
            ['type_shop_id', $Sale->type_shop_id],
            ['sales_products_status_id', $SaleProduct->status_id],
            ['calc', 1],
        ])->pluck('income_id')->toArray(); // get ids for calculating costs

        $incomesValue = 0;
        foreach($Sale->incomes->whereIn('income_id', $SaleProductStatusCalculatedIncomesIds) as $SaleProductStatusIncome)
        {
            $incomesValue
                += self::getIncomeOrCostValue($SaleProductStatusIncome, $SaleProduct, $Sale->type_shop_id)
                * $SaleProduct->product_quantity;
        }
        return $incomesValue;
    }
}


