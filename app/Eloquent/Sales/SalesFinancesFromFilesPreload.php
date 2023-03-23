<?php

namespace App\Eloquent\Sales;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SalesFinancesFromFilesPreload extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price' => 'float',
        'compare_commission' => 'float',
        'ozon_price_1' => 'float',
        'ozon_price_2' => 'float',
        'ozon_price_3' => 'float',
    ];

    public function wbData()
    {
        return $this->hasOne('App\Eloquent\Other\WB\WbReportsData', 'id', 'wb_data_id');
    }

    public function service()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesFinancesService', 'id', 'service_id');
    }

    public function finance()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesFinancesName', 'id', 'finance_id');
    }

    public function getDateFormatAttribute()
    {
        return Carbon::parse($this->date)->format('Y-m-d');
    }

    public function file()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesFinancesFile', 'id', 'file_id');
    }

    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'id', 'sale_id');
    }

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'id', 'product_id');
    }

    public function productNewStatus()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesProductsStatus', 'id', 'set_sale_product_status_id');
    }

    public function getSaleProductAttribute()
    {
        return SalesProduct::where([
            ['sale_id', $this->sale_id],
            ['product_id', $this->product_id],
        ])->first();
    }

    public function getWarningAttribute()
    {
        $warning = '';

        if(!$this->Sale)
        {
            if($this->sale_create)
            {
                $warning .= "Создание продажи №$this->sale_create<br/>";
            }else
            {
                $warning .= 'Не найдена продажа: строка будет пропущена<br/>';
            }
        }

        $SaleProduct = $this->SaleProduct;
        if($this->service_id === 24)
        {
            $warning .= 'Возврат средств<br/>';
            if(!$SaleProduct)
            {
                $warning .= 'Не найден продукт по возврату<br/>';
            }
        }
        if($this->auto_settled_status) $warning .= 'Замена статуса<br/>';


        // newest
        $totalProductQuantity = (int) SalesFinancesFromFilesPreload::where([
            ['file_id', $this->file_id],
            ['shop_id', $this->shop_id],
            ['sale_id', $this->sale_id],
            ['service_id', $this->service_id],
            ['product_id', $this->product_id],
            ['price', $this->price],
            ['product_quantity', $this->product_quantity],
        ])->sum('product_quantity');

        //if($SaleProduct and ($SaleProduct->product_quantity !== $this->product_quantity))
        if($SaleProduct and ($SaleProduct->product_quantity !== $totalProductQuantity))
        {
            $warning .= 'Количество товара не совпадает<br/>';
        }


        if($this->is_return and ($tableClassMess = $this->getTableClassAttribute(true))) $warning .= $tableClassMess;

        return $warning;
    }

    public function getExistAttribute()
    {
        $q = [
            'shop_id' => $this->shop_id,
            'date' => $this->date,
            'price' => $this->price,
            'order_number' => $this->order_number,
            'service_id' => $this->service_id,
        ];

        if($this->finance_number) $q['finance_number'] = $this->finance_number;
        if($this->product_id) $q['product_id'] = $this->product_id;
        if($this->finance_id) $q['finance_id'] = $this->finance_id;

        return SalesFinancesFromFile::where($q)->first();
    }


    public function getSaleCompareCommissionAttribute()
    {
        return $this->SaleProduct->commission_percent??NULL;
    }

    public function getSaleCompareCommissionClassAttribute()
    {
        $class = '';
        if(round($this->SaleCompareCommission, 2) !== round(8.1, 2)) $class = 'badge-warning';

        return $class;
    }

    public function getCalcOzonCheckField1Attribute()
    {
        return round($this->ozon_price_3 * $this->product_quantity * ((100 - $this->compare_commission)/100), 2);
    }
    public function getCalcOzonCheckField1FormulaAttribute()
    {
        return "Округлить до сотых ({$this->ozon_price_3}₽ * {$this->product_quantity}шт. * ((100 - $this->compare_commission)/100))";
    }

    public function getCalcOzonCheckField2Attribute()
    {
        return round($this->price - $this->CalcOzonCheckField1, 2);
    }
    public function getCalcOzonCheckField2FormulaAttribute()
    {
        return "Округлить до сотых ({$this->price}₽ - {$this->CalcOzonCheckField1}₽ (левое поле проверки) )";
    }



    public function getTableClassAttribute($returnMessage = false): string
    {
        //Прямая транзакция есть в Продаже - выделяем жёлтым цветом
        $class = 'red';
        $mess = '';

        if(count($this->DirectTransactionInPreload) > 0)
        {
            $class = 'yellow';
            $sumInPreload = $this->DirectTransactionInPreload->sum('price');

            //dd($sumInPreload, $this->price);
            if(abs($sumInPreload) != abs($this->price))
            {
                $class = 'red';
                $mess = 'Сумма прямых транзакций в этом файле не равна этой возвратной транзакции.';
            }
        }

        if(count($this->DirectTransactionInSale) > 0)
        {
            $class = 'yellow';
            $sumInSale = $this->DirectTransactionInSale->sum('price');

            if(abs($sumInSale) != abs($this->price))
            {
                $class = 'red';
                $mess = 'Сумма прямых транзакций в продаже не равна этой возвратной транзакции.';
            }
        }

        if(
            (count($this->DirectTransactionInPreload)  > 0)
            and
            (count($this->DirectTransactionInSale)) > 0)
        {
            $class = 'orange';
            $mess = 'Прямая транзакция есть в этом файле и в соответствующей продаже.';
            //есть в Продаже, нет в Файле
            //есть в Файле, нет в Продаже
        }

        return $returnMessage?$mess:$class;
    }

    public function getDirectTransactionInSaleAttribute()
    {
        return SalesFinancesFromFile::where([
            'sale_id' => $this->sale_id,
            'product_id' => $this->product_id,

            'is_return' => false,
        ])->whereHas('service', function($q)
        {
            $q->whereNull('cost_id');
        })
        ->get();
    }

    public function getDirectTransactionInPreloadAttribute()
    {
        return SalesFinancesFromFilesPreload::where([
            'file_id' => $this->file_id,
            'sale_id' => $this->sale_id,
            'product_id' => $this->product_id,

            'is_return' => false,
        ])->get();
    }

    public function getSkuWarningAttribute()
    {
        if(!$this->product) return true;
        if($this->product->sku != $this->product_sku) return true;
        return false;
    }

    public function getWbCommissionCheckClassAttribute()
    {
        $loadCommission = round($this->wbData->calc_commission_percent??0, 2);
        $saleCommission = round($this->SaleProduct->commission_percent??0, 2);

        if($loadCommission !== $saleCommission)
        {
            return 'badge-danger';
        }

        return '';
    }
}
