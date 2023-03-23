<?php

namespace App\Models\Sales;

use App\Eloquent\Sales\SalesProductsStatus;
use App\Eloquent\System\SystemsMarginCalcByStatus;
use App\Eloquent\System\SystemsMarginCalcCostsByStatus;
use App\Eloquent\System\SystemsMarginCalcIncomesByStatus;
use App\Models\Model;

class SalesProductsStatuses extends Model{

    public static function changeMarginRecalculatesFromForm($calculating, $productsStatusId)
    {
        self::changeMarginCalcFromForm($calculating['marginCalcs']??NULL, $productsStatusId);
        self::changeMarginCostsCalcsFromForm($calculating['marginCostsCalcs']??NULL, $productsStatusId);
        self::changeMarginIncomesCalcsFromForm($calculating['marginIncomesCalcs']??NULL, $productsStatusId);
    }

    public static function changeMarginCalcFromForm($marginCalcs, $productsStatusId)
    {
        $marginCalcsNotRemoveIds = [];
        if($marginCalcs)
        {
            foreach($marginCalcs as $typeShopId => $marginCalc)
            {
                $SystemsMarginCalcByStatus = SystemsMarginCalcByStatus::firstOrNew([
                    'sales_products_status_id' => $productsStatusId,
                    'type_shop_id' => $typeShopId
                ]);
                $SystemsMarginCalcByStatus->price = isset($marginCalc['price'])?1:0;
                $SystemsMarginCalcByStatus->purchase_price = isset($marginCalc['purchase_price'])?1:0;
                $SystemsMarginCalcByStatus->commission = isset($marginCalc['commission'])?1:0;
                $SystemsMarginCalcByStatus->commission_deduction = isset($marginCalc['commission_deduction'])?1:0;
                $SystemsMarginCalcByStatus->incomes = isset($marginCalc['incomes'])?1:0;
                $SystemsMarginCalcByStatus->costs = isset($marginCalc['costs'])?1:0;
                $SystemsMarginCalcByStatus->save();

                $marginCalcsNotRemoveIds[] = $SystemsMarginCalcByStatus->id;
            }
        }

        SystemsMarginCalcByStatus
            ::where('sales_products_status_id', $productsStatusId)
            ->whereNotIn('id', $marginCalcsNotRemoveIds)->delete();

        // there is removing all, but not in array
    }

    public static function changeMarginCostsCalcsFromForm($marginCostsCalcs, $productsStatusId)
    {
        $marginCostsCalcsNotRemoveIds = [];

        if($marginCostsCalcs)
        {
            foreach($marginCostsCalcs as $typeShopId => $marginCostsCalc)
            {
                foreach($marginCostsCalc as $costId => $on)
                {
                    $SystemsMarginCalcCostsByStatus = SystemsMarginCalcCostsByStatus::firstOrNew([
                        'sales_products_status_id' => $productsStatusId,
                        'type_shop_id' => $typeShopId,
                        'cost_id' => $costId
                    ]);
                    $SystemsMarginCalcCostsByStatus->calc = 1;
                    $SystemsMarginCalcCostsByStatus->save();

                    $marginCostsCalcsNotRemoveIds[] = $SystemsMarginCalcCostsByStatus->id;
                }
            }
        }

        // there is removing all, but not in array
        SystemsMarginCalcCostsByStatus
            ::where('sales_products_status_id', $productsStatusId)
            ->whereNotIn('id', $marginCostsCalcsNotRemoveIds)->delete();
    }

    public static function changeMarginIncomesCalcsFromForm($marginIncomesCalcs, $productsStatusId)
    {
        $marginIncomesCalcsNotRemoveIds = [];

        if($marginIncomesCalcs)
        {
            foreach($marginIncomesCalcs as $typeShopId => $marginIncomesCalc)
            {
                foreach($marginIncomesCalc as $incomeId => $on)
                {
                    $SystemsMarginCalcIncomesByStatus = SystemsMarginCalcIncomesByStatus::firstOrNew([
                        'sales_products_status_id' => $productsStatusId,
                        'type_shop_id' => $typeShopId,
                        'income_id' => $incomeId
                    ]);
                    $SystemsMarginCalcIncomesByStatus->calc = 1;
                    $SystemsMarginCalcIncomesByStatus->save();

                    $marginIncomesCalcsNotRemoveIds[] = $SystemsMarginCalcIncomesByStatus->id;
                }
            }
        }

        // there is removing all, but not in array
        SystemsMarginCalcIncomesByStatus
            ::where('sales_products_status_id', $productsStatusId)
            ->whereNotIn('id', $marginIncomesCalcsNotRemoveIds)
            ->delete();
    }

    public static function updateStatusFromForm($request, $id = false)
    {
        if($id)
        {
            $SalesProductsStatus = SalesProductsStatus::where('id', $id)->firstOrFail();
        }else{
            $SalesProductsStatus = new SalesProductsStatus;
        }

        $SalesProductsStatus->state = $request->input('state');
        $SalesProductsStatus->name = $request->input('name');
        $SalesProductsStatus->sale_products_statuses_group_id = $request->input('sale_products_statuses_group_id');
        $SalesProductsStatus->comment = $request->input('comment');
        $save = $SalesProductsStatus->save();

        if($save)
        {
            self::changeMarginRecalculatesFromForm($request->input('calculating', NULL), $SalesProductsStatus->id);
            return $SalesProductsStatus->id;
        }else{
            return false;
        }
    }
}


