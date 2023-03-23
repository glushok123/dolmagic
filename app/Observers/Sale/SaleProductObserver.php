<?php

namespace App\Observers\Sale;

use App\Eloquent\Sales\SalesCost;
use App\Eloquent\Sales\SalesProduct;
use App\Eloquent\Sales\SalesProductsStatusesHistory;
use App\Models\Sales;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;


class SaleProductObserver
{
    public $historyPrefix = 'sale-product-';

    public function creating(SalesProduct $SalesProduct)
    {
        $Sale = $SalesProduct->sale??false;

        // WB
        if($Sale and in_array($Sale->type_shop_id, [177, 179]))
        {
            $SalesProduct->product_spp = $SalesProduct->product_price;
        }
    }

    public function created(SalesProduct $SalesProduct)
    {
        try
        {
            Sales::historyAdd(
                $SalesProduct->sale_id,
                $SalesProduct,
                $this->historyPrefix . 'created'
            );
        }catch(\Exception $e){}

        try
        {
            if($Sale = $SalesProduct->sale)
                Sales::recalculateCommission($Sale);

        }catch(\Exception $e){}
    }

    public function deleted(SalesProduct $SalesProduct)
    {
        Sales::historyAdd(
            $SalesProduct->sale_id,
            $SalesProduct,
            $this->historyPrefix . 'deleted'
        );
    }

    public function updated(SalesProduct $SalesProduct)
    {
        $changes = $SalesProduct->isDirty() ? $SalesProduct->getDirty() : false;
        if($changes) {
            foreach($changes as $attr => $value) {
                Sales::historyAdd(
                    $SalesProduct->sale_id,
                    $SalesProduct,
                    $this->historyPrefix . $attr,
                    $SalesProduct->getOriginal($attr),
                    $SalesProduct->$attr,
                    $SalesProduct->getOriginal() // ?
                );

                if(in_array($attr, ['status_id', 'product_quantity']))
                {
                    if($Sale = $SalesProduct->sale)
                    {
                        if($attr === 'status_id')
                        {
                            if(($Sale->type_shop_id === 67) and (in_array($SalesProduct->status_id, [4, 5, 9, 10]))) // returns
                            {
                                try
                                {
                                    $SalesCost = SalesCost::firstOrNew([
                                        'sale_id' => $Sale->id,
                                        'cost_id' => 27
                                    ]);
                                    $SalesCost->comment = 'Авто-расход (сервер 2)';
                                    $SalesCost->value = '30';
                                    $SalesCost->value_type_id = 1;
                                    $SalesCost->flow_type_id = 2;
                                    $SalesCost->save();
                                }catch(\Exception $e){}
                            }
                        }
                    }

                    if($attr === 'status_id')
                    {
                        $SalesProductsStatusesHistory = new SalesProductsStatusesHistory;
                        $SalesProductsStatusesHistory->sale_id = $SalesProduct->sale_id;
                        $SalesProductsStatusesHistory->sales_product_id = $SalesProduct->id;
                        $SalesProductsStatusesHistory->old_status_id = $SalesProduct->getOriginal($attr);
                        $SalesProductsStatusesHistory->new_status_id = $SalesProduct->$attr;
                        $SalesProductsStatusesHistory->change_time = Carbon::now();
                        $SalesProductsStatusesHistory->save();
                    }

                    Sales::updateData($Sale);
                };
            };
        }
    }
}
