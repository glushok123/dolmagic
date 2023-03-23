<?php

namespace App\Eloquent\Sales;

use App\Eloquent\Other\WB\WbReportsData;
use App\Eloquent\System\SystemsCommission;
use App\Models\Others\Wildberries\WildberriesReports;
use App\Models\Sales\SalesFinances;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sales;
use App\Observers\Sale\SaleObserver;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    protected $guarded = array();

    protected $casts = [
        'margin_value_calced' => 'float',
    ];

    public static function boot()
    {
        parent::boot();
        static::observe(new SaleObserver());
    }


    public function ozonFboReturns()
    {
        return $this->hasMany('App\Eloquent\Other\Ozon\OzonFboReturn', 'sale_id', 'id');
    }

    public function ozonFbsReturns()
    {
        return $this->hasMany('App\Eloquent\Other\Ozon\OzonFbsReturn', 'sale_id', 'id');
    }

    public function getHasAnyReturnsAttribute()
    {
        if(count($this->ozonFboReturns) > 0) return true;
        if(count($this->ozonFbsReturns) > 0) return true;
        return false;
    }

    public function financesFromFile()
    {
        return $this->hasMany('App\Eloquent\Sales\SalesFinancesFromFile', 'sale_id', 'id');
    }


    public function compensations()
    {
        return $this->hasMany('App\Eloquent\Sales\SalesCompensation', 'sale_id', 'id');
    }

    public function history()
    {
        return $this->hasMany('App\Eloquent\Sales\SalesHistory', 'sale_id', 'id');
    }

    public function packs()
    {
        return $this->hasMany('App\Eloquent\Sales\SalesProductsPack', 'sale_id', 'id');
    }

    public function products()
    {
        return $this->hasMany('App\Eloquent\Sales\SalesProduct', 'sale_id', 'id');
    }

    public function productsStatusesHistories()
    {
        return $this->hasMany('App\Eloquent\Sales\SalesProductsStatusesHistory', 'sale_id', 'id');
    }

    public function system()
    {
        return $this->hasOne('App\Eloquent\System\System', 'id', 'system_id');
    }

    public function order()
    {
        return $this->hasOne('App\Eloquent\Order\Order', 'id', 'order_id');
    }

    public function typeShop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'type_shop_id');
    }

    public function shop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'type_shop_id');
    }

    public function incomes()
    {
        return $this->hasMany('App\Eloquent\Sales\SalesIncome', 'sale_id', 'id');
    }

    public function costs()
    {
        return $this->hasMany('App\Eloquent\Sales\SalesCost', 'sale_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany('App\Eloquent\System\SystemsTransaction', 'sale_id', 'id')
            ->orderBy('created_at', 'DESC')
            ->orderBy('transaction_type_id', 'DESC');
    }

    public function salesIntermediaryCommission()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesIntermediaryCommission', 'sale_id', 'id');
    }

    public function movements()
    {
        return $this->hasMany('App\Eloquent\Warehouse\WarehouseMovement', 'sale_id', 'id');
    }



    /* For view */
    public function getDateSaleTimeAttribute()
    {
        return explode(' ', $this->date_sale)[1];
    }

    public function accrualDate()
    {
        return $this->hasOne('App\Eloquent\System\SystemsTransaction', 'sale_id', 'id')
            ->selectRaw('sale_id, max(transaction_date) as aggregate')
            ->groupBy('sale_id');
    }

    public function getAccrualDateAttribute()
    {
        // if relation is not loaded already, let's do it first
        if ( ! array_key_exists('accrualDate', $this->relations))
            $this->load('accrualDate');

        $related = $this->getRelation('accrualDate');

        // then return the count directly
        return ($related) ? $related->aggregate : NULL;
    }

    public function accrualValue()
    {
        return $this->hasOne('App\Eloquent\System\SystemsTransaction', 'sale_id', 'id')
            //->selectRaw('sale_id,  SUM(order_amount) + SUM(discount_amount) + SUM(commission_amount) + SUM(item_delivery_amount) + SUM(item_return_amount) AS aggregate')
            ->selectRaw('sale_id,  SUM(total_amount) AS aggregate')

            ->groupBy('sale_id');
    }

    public function getAccrualValueAttribute()
    {
        // if relation is not loaded already, let's do it first
        if ( ! array_key_exists('accrualValue', $this->relations))
            $this->load('accrualValue');

        $related = $this->getRelation('accrualValue');

        // then return the count directly
        return ($related) ? (float) $related->aggregate : 0;
    }

    /* Products count in filter and any */
    public function productsQuantity()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesProduct', 'sale_id', 'id')
            ->selectRaw('sale_id, sum(product_quantity) as aggregate')
            ->groupBy('sale_id');
    }

    public function getProductsQuantityAttribute()
    {
        // if relation is not loaded already, let's do it first
        if ( ! array_key_exists('productsQuantity', $this->relations))
            $this->load('productsQuantity');

        $related = $this->getRelation('productsQuantity');

        // then return the count directly
        return ($related) ? (int) $related->aggregate : 0;
    }


    public function getTotalPayableAttribute()
    {
        return Sales::getTotalPayable($this);
    }


    public function getAccrualLackAttribute()
    {
        return intval(($this->TotalAmount - $this->TransactionTotalAmount)*100)/100;
    }

    public function getTransactionTotalAmountAttribute()
    {
        return $this->transactions->sum('total_amount');
    }


//number_format($paginateSystemsTransactions->sum('sale.TotalAmount') - $paginateSystemsTransactions->sum('total_amount'), 2, ',', ' ')

    public function getTotalAmountAttribute()
    {
        return $this->TotalSalePrice - $this->Commission - $this->SystemDeliveryRub - $this->SystemDeliveryReturnRub;
    }


    // GET

    // all values in one object!

    public function getAllAmountsValueAttribute()
    {
        return Sales::getAllAmountsValue($this);
    }

    public function getTotalSalePriceAttribute()
    {
        return $this->AllAmountsValue->price;
    }

    public function getTotalPurchasePriceAttribute()
    {
        return $this->AllAmountsValue->purchasePrice;
    }

    public function getCommissionAttribute()
    {
        return $this->AllAmountsValue->commission;
    }

    public function getIncomesValueAttribute()
    {
        return $this->AllAmountsValue->incomes;
    }

    public function getCostsValueAttribute()
    {
        return $this->AllAmountsValue->costs;
    }

    public function getMarginValueAttribute()
    {
        return $this->AllAmountsValue->margin;
    }

    public function getTotalSalePriceWithIncomesValueAttribute()
    {
        return $this->TotalSalePrice  + $this->IncomesValue;
    }

    public function getTotalSaleTotalPriceWithoutCommissionAttribute()
    {
        return $this->AllAmountsValue->TotalSaleTotalPriceWithoutCommission;

        $value = 0;
        foreach($this->products as $SaleProduct)
        {
            $value += $SaleProduct->TotalPriceWithoutCommission;
        }
        return $value;
    }




    public function getTotalDeliveryCostAttribute()
    {
        return Sales::getTotalDeliveryCost($this);
    }



    public function getTotalPriceAttribute()
    {
        return Sales::getTotalPrice($this);
    }









    public function getSystemDeliveryRubAttribute()
    {
        return Sales::getSystemDeliveryRub($this);
    }

    public function getSystemDeliveryReturnRubAttribute()
    {
        return Sales::getSystemDeliveryReturnRub($this);
    }

    public function getDiscrepancyAttribute() // it's to del
    {
        return intval(($this->totalSalePrice - $this->Commission - $this->accrualValue)*100)/100;
    }







    public function getDateCreateTextAttribute()
    {
        $date = Carbon::createFromTimeString($this->created_at, 'Europe/Moscow');
        $dateString = $date->format('d.m.y H:i');
        return $dateString;
    }

    public function getDateSaleTextAttribute()
    {
        $dateString = '';
        if($this->date_sale){
            $date = Carbon::createFromTimeString($this->date_sale, 'Europe/Moscow');
            $dateString = $date->format('d.m.y H:i');
        };
        return $dateString;
    }

    public function getSaleDayAttribute()
    {
        $dateString = '';
        if($this->date_sale){
            $date = Carbon::createFromTimeString($this->date_sale, 'Europe/Moscow');
            $dateString = $date->format('d.m.y');
        };
        return $dateString;
    }

    public function getClassAttribute()
    {
        $statusName = $this->statusName;
        $statusClass = '';

        $FirstPack = $this->packs->first();
        if(!$FirstPack OR !$FirstPack->carrier_id OR !$FirstPack->departure_date)
        {
            $statusClass = ' table-info';
        }

        if($FirstPack)
        {
            foreach($FirstPack->products as $FirstPackProduct)
            {
                if($FirstPackProduct->purchase_price == 0) $statusClass = ' table-info';
            }
        }


        $haveDeliveryCost = false;

        $Order = $this->order;
        if($Order) $orderTypeShopId = $Order->typeShop->id;

        foreach($this->costs as $SaleCost)
        {
            if($SaleCost->cost_id === 11) $haveDeliveryCost = true;

            if(isset($orderTypeShopId) and $orderTypeShopId === 1 and $SaleCost->cost_id === 14)
                $haveDeliveryCost = true; // FOR OZON STV
        }

        if(!$haveDeliveryCost) $statusClass = ' table-warning';


        switch($statusName){
            case 'ВОЗВРАТ ':
            case 'ВОЗВРАТ ПОСЛЕ ПОЛУЧЕНИЯ ':
            case 'ВОЗВРАТ В ПУТИ ':
            case 'ВОЗВРАТ В ПУТИ ПОСЛЕ ПОЛУЧЕНИЯ ':
            case 'ОТМЕНА ':
            case 'ОТМЕНА ОЗОН ':
                    $statusClass = ' table-danger';
                break;

            case 'ПРОДАНО ':
                    if($haveDeliveryCost) $statusClass = ' table-success';
                break;
            case 'СПИСАН ':
                    if($haveDeliveryCost) $statusClass = ' table-success';
                break;
        }

        return $statusClass;
    }

    public function getStatusNameAttribute()
    {
        $statusName = '';
        //"ЗАКАЗ", "ПРОДАНО", "ВОЗВРАТ В ПУТИ", "ВОЗВРАТ"
        $order = false; //"ЗАКАЗ"
        $sold = false; //"ПРОДАНО"
        $return = false; //"ВОЗВРАТ"
        $returnOnWay = false; //"ВОЗВРАТ В ПУТИ"
        $canceled = false; // "ОТМЕНА "
        $canceledOzon = false; // "ОТМЕНА OZON"
        $returnOnWayAfterReceived = false;
        $returnAfterReceived = false;
        $wroteOff = false;

        if($this->prepayment_made != 0 AND $this->prepayment_made === $this->totalSalePrice){
            $sold = true;
        }

        $default = '';
        foreach($this->products as $SaleProduct)
        {
            switch($SaleProduct->status_id){
                case 1: // Заказ
                        $order = true;
                    break;
                case 2: // Продано
                        $sold = true;
                    break;
                case 3: // Отмена
                        $canceled = true;
                    break;
                case 6: // Отмена
                    $canceledOzon = true;
                    break;
                case 4: // Возврат
                        $return = true;
                    break;
                case 5: // Возврат в пути
                        $returnOnWay = true;
                    break;
                case 10:
                        $returnOnWayAfterReceived = true;
                    break;
                case 9:
                        $returnAfterReceived = true;
                    break;
                case 13: // СПИСАН
                        $wroteOff = true;
                    break;
                default:
                    $default = $SaleProduct->status->name;
            }
        }

        if($order) $statusName .= 'ЗАКАЗ ';
        if($sold) $statusName .= 'ПРОДАНО ';
        if($wroteOff) $statusName .= 'СПИСАН ';
        if($canceled) $statusName .= 'ОТМЕНА ';
        if($canceledOzon) $statusName .= 'ОТМЕНА ОЗОН ';
        if($return) $statusName .= 'ВОЗВРАТ ';
        if($returnOnWay) $statusName .= 'ВОЗВРАТ В ПУТИ ';
        if($returnOnWayAfterReceived) $statusName .= 'ВОЗВРАТ В ПУТИ ПОСЛЕ ПОЛУЧЕНИЯ ';
        if($returnAfterReceived) $statusName .= 'ВОЗВРАТ ПОСЛЕ ПОЛУЧЕНИЯ ';
        if($default) $statusName .= $default;

        return $statusName;
    }

    public function getEditPathAttribute()
    {
        return route('sales.edit', ['id' => $this->id]);
    }

    public function getEditMainPathAttribute()
    {
        return route('sales.edit.main', ['id' => $this->id]);
    }

    public function getEditReturnsPathAttribute()
    {
        return route('sales.edit.returns', ['id' => $this->id]);
    }

    public function getStrPadIdAttribute()
    {
        return str_pad($this->id,5,'0',STR_PAD_LEFT);
    }

    public function getAccountsReceivableAttribute()
    {
        return Sales::getAccountsReceivable($this);
    }

    public function getIntermediaryCommissionAttribute()
    {
        return Sales::getIntermediaryCommission($this);
    }

    public function getOrderNumberAttribute()
    {
        $orderNumber = "id:$this->id";

        if(!empty($this->manual_order_number))
        {
            $orderNumber = $this->manual_order_number;
        }else{
            if(isset($this->order) and !empty($this->order->order_system_number))
            {
                $orderNumber = $this->order->order_system_number;
            }
        }

        return $orderNumber;
    }

    public function getTotalFinancesFromFileAttribute()
    {
        return SalesFinances::getSaleIncomeFromFileTotal($this);
    }

    public function getReturnPeriodDaysAttribute()
    {
        return Sales::getReturnPeriodDays($this);
    }

    public function getDeliveryPeriodDaysAttribute()
    {
        return Sales::getDeliveryPeriodDays($this);
    }

    public function getUponReceiptAttribute()
    {
        return $this->order->info->paymentType->upon_receipt??NULL;
    }


    public function getShowFastMovementAttribute()
    {
        foreach($this->packs as $SalePack)
        {
            if(!$SalePack->carrier_id or !$SalePack->departure_date)
                return false;
        }

        return true;
    }

    public function getCanReturnStocksAttribute()
    {
        $canReturnStocks = false;
        foreach($this->products as $SaleProduct)
        {
            if($SaleProduct->PostingBalance > 0)
                $canReturnStocks = true;
        }
        return $canReturnStocks;
    }

    public function getHasPseudoWriteOffAttribute()
    {
        if((count($this->movements) === 0) or !$this->CanReturnStocks)
        {
            return false;
        }else
        {
            return true;
        }
    }

    public function getCanSetPurchasePriceAttribute()
    {
        $canSetPurchasePrice = true;
        foreach($this->products as $SaleProduct)
        {
            if($SaleProduct->purchase_price)
                $canSetPurchasePrice = false;
        }
        return $canSetPurchasePrice;
    }

    public function getOrderURLAttribute()
    {
        $url = '';
        if(isset($this->typeShop))
        {
            if($this->typeShop->order_url) $url = $this->typeShop->order_url;
        }

        if(!$url)
        {
            if(isset($this->system))
            {
                if($this->system->order_url) $url = $this->system->order_url;
            }
        }

        return $url;
    }

    public function getTotalWeightAttribute()
    {
        $totalWeight = 0;
        foreach($this->products as $SaleProduct)
        {
            if($SaleProduct->product)
            {
                $totalWeight += $SaleProduct->product->weight * $SaleProduct->product_quantity;
            }
        }

        return $totalWeight;
    }

    public function getTotalEstimatedWeightAttribute()
    {
        $totalEstimatedWeight = 0;
        foreach($this->products as $SaleProduct)
        {
            if($SaleProduct->product)
            {
                //$totalClearEstimatedWeight += $SaleProduct->TotalClearEstimatedWeight;
                $totalEstimatedWeight += $SaleProduct->TotalEstimatedWeight;
            }
        }

        return round($totalEstimatedWeight, 1);
    }



    public function getReturnProductQuantityAttribute()
    {
        $returnQuantity = 0;
        foreach($this->products as $SaleProduct)
        {
            if($SaleProduct->status->is_return_status)
                $returnQuantity += $SaleProduct->product_quantity;

        }

        return $returnQuantity;
    }

    public function getShopIdAttribute()
    {
        return $this->type_shop_id;
    }

    public function getProductsQuantityBySkuAttribute($sku)
    {
        return SalesProduct::where('sale_id', $this->id)->whereHas('product', function($q) use ($sku)
        {
            $q->where('sku', $sku);
        })->sum('product_quantity');
    }

    public function getCostsPercentAttribute()
    {
        return ($this->CostsValue + $this->Commission) / $this->TotalSalePrice * 100;
    }

    public function getMarginPercentAttribute()
    {
        return Sales::getMarginPercent($this);
    }

    public function getAHrefAttribute(): string
    {
        return "<a target = '_blank' href = '$this->EditPath'>$this->OrderNumber</a>";
    }


    public function getCommissionPercentAttribute()
    {
        $countProducts = count($this->products);
        if($countProducts > 0)
        {
            return round($this->products->sum('commission_percent') / $countProducts, 2);
        }else
        {
            return 0;
        }

    }

    public function getCommissionPercentByDateAttribute()
    {
        $commission = 0;
        $saleDate = Carbon::parse($this->date_sale)->setTimezone('Europe/Moscow')->format('Y-m-d');
        if($SystemsCommission = SystemsCommission
            ::where('system_id', $this->system_id)
            ->where(function($query) use ($saleDate)
            {
                $query->where(function($query2) use ($saleDate) {
                    $query2->where([['used_since', '<=', $saleDate]])->orWhereNull('used_since');
                })
                    ->where(function($query2) use ($saleDate)
                    {
                        $query2->where([['used_to', '>=', $saleDate]])->orWhereNull('used_to');
                    });
            })->first())
        {
            $commission = round($SystemsCommission->value_percent, 2);
        }


        return $commission;
    }

    public function getWBCommissionPercentAttribute()
    {
        $commission = 0;

        //if($WbReportsData = WbReportsData::where('sale_id', $this->id)->where('calc_commission_percent', '!=', 0)->first())
        //{
        //    $commission = round($WbReportsData->calc_commission_percent, 2);
        //}

        if($commission = SalesFinancesFromFile::where([
            ['sale_id', $this->id],
        ])->whereHas('service', function($q)
        {
            $q->whereNull('cost_id');
        })->sum('compare_commission'))
        {
            $commission = round($commission, 2);
        }



        return $commission;
    }

    public function getChargesIncomeAttribute()
    {
        return SalesFinancesFromFile::where('sale_id', $this->id)->whereHas('service', function($q)
        {
            $q->whereNull('cost_id');
        })->count();
    }


    public function getHasWBTotalFinancesFromFileAttribute()
    {
        return (SalesFinancesFromFile::where([
            ['sale_id', $this->id],
        ])->whereHas('service', function($q)
        {
            $q->whereNull('cost_id');
        })->count() > 0);
    }

    public function getWBTotalFinancesFromFileAttribute()
    {
        $price = SalesFinancesFromFile::where([
            ['sale_id', $this->id],
        ])->whereHas('service', function($q)
        {
            $q->whereNull('cost_id');
        })->sum('price');

        return round($price, 2);
    }

    public function getSameSalesAttribute()
    {
        $res = new \stdClass();
        $res->time = 0;
        $res->list = collect();

        $start = microtime(true);

        if(!$key = $this->OrderNumber?explode('-', $this->OrderNumber)[0]:false)
            return $res;

        $res->list = Sale::whereHas('order', function($q) use ($key)
        {
            $q->where('order_system_number', 'LIKE', "$key-%");
        })->whereHas('shop', function($q)
        {
            $q->where('type', 'Ozon');
        })
            //->where('id', '!=', $this->id)
            ->orderBy('date_sale', 'DESC')
            ->get();

        $res->time = round(microtime(true) - $start, 4);

        return $res;
    }



    public function getHasWBLogisticsAmountAttribute()
    {
        return $this->costs->whereIn('cost_id', WildberriesReports::getLogisticsCostIds())->count() > 0;
    }

    public function getWBLogisticsAmountAttribute()
    {
        return $this->costs->whereIn('cost_id', WildberriesReports::getLogisticsCostIds())->sum('value');
    }

    public function getHasWBReportLogisticsAmountAttribute()
    {
        return SalesFinancesFromFile::where([
                ['sale_id', $this->id],
            ])->whereHas('service', function($q)
            {
                $q->whereIn('cost_id', WildberriesReports::getLogisticsCostIds());
            })->count() > 0;
    }

    public function getWBReportLogisticsAmountAttribute()
    {
        $price = SalesFinancesFromFile::where([
            ['sale_id', $this->id],
        ])->whereHas('service', function($q)
        {
            $q->whereIn('cost_id', WildberriesReports::getLogisticsCostIds());
        })->sum('price');

        return round($price, 2);
    }

    public function getHasWBPenaltyAmountAttribute()
    {
        return $this->costs->whereIn('cost_id', [55])->count() > 0;
    }

    public function getWBPenaltyAmountAttribute()
    {
        return $this->costs->whereIn('cost_id', [55])->sum('value');
    }

    public function getHasWBReportPenaltyAmountAttribute()
    {
        return SalesFinancesFromFile::where([
                ['sale_id', $this->id],
            ])->whereHas('service', function($q)
            {
                $q->whereIn('cost_id', [55]);
            })->count() > 0;
    }

    public function getWBReportPenaltyAmountAttribute()
    {
        $price = SalesFinancesFromFile::where([
            ['sale_id', $this->id],
        ])->whereHas('service', function($q)
        {
            $q->whereIn('cost_id', [55]);
        })->sum('price');

        return round($price, 2);
    }






}

