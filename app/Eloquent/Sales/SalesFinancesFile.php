<?php

namespace App\Eloquent\Sales;

use App\Eloquent\Other\WB\WbReport;
use App\Models\Sales\SalesProducts;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesFinancesFile extends Model
{

    public function allTypePreloadedFinances()
    {
        return $this
            ->hasMany('App\Eloquent\Sales\SalesFinancesFromFilesPreload', 'file_id', 'id');
    }

    public function preloadedFinances()
    {
        return $this
            ->hasMany('App\Eloquent\Sales\SalesFinancesFromFilesPreload', 'file_id', 'id')
            ->where('is_return', 0);
    }

    public function preloadedFinancesReturns()
    {
        return $this
            ->hasMany('App\Eloquent\Sales\SalesFinancesFromFilesPreload', 'file_id', 'id')
            ->where('is_return', 1);
    }

    public function wbReport()
    {
        return $this->hasOne('App\Eloquent\Other\WB\WbReport', 'id', 'wb_report_id');
    }

    public function shop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'shop_id');
    }

    public function getFileSrcAttribute()
    {
        if($this->wb_report_id)
        {

            return $this->wbReport->EditPath;
        }else
        {
            return asset('/storage/files/sales/finances/'.$this->filename);
        }
    }

    public function getLocalPathAttribute()
    {
        return 'files/sales/finances/'.$this->filename;
    }

    public function getDateTimeAttribute()
    {
        return Carbon::parse($this->created_at)->setTimezone('Europe/Moscow')->toDateTimeString();
    }

    public function getATagAttribute()
    {
        if($this->wb_report_id)
        {

            return "<a target = '_blank' href = '{$this->wbReport->EditPath}'>API Отчёты - {$this->wbReport->Title}</a>";
        }else
        {
            return "<a href = '$this->FileSrc'>$this->Title</a>";
        }
    }



    public function getCountWithoutSaleAttribute()
    {
        return SalesFinancesFromFilesPreload::where([
            ['file_id', $this->id],
            ['sale_id', NULL],
        ])->count();
    }

    public function getCountPreloadedFinancesAttribute()
    {
        return SalesFinancesFromFilesPreload::where([
            ['file_id', $this->id],
        ])->count();
    }

    public function getCountReturnFinancesAttribute()
    {
        return SalesFinancesFromFilesPreload::where([
            ['file_id', $this->id],
            ['is_return', 1],
        ])->count();
    }

    public function getCountChangeProductStatusesFinancesAttribute()
    {
        return SalesFinancesFromFilesPreload::where([
            ['file_id', $this->id],
        ])->whereNotNull('set_sale_product_status_id')->count();
    }

    public function getCountChangeProductStatusesWithoutProductFinancesAttribute()
    {
        return SalesFinancesFromFilesPreload::where([
            ['file_id', $this->id],
        ])->whereNotNull('set_sale_product_status_id')->whereNull('product_id')->count();
    }

    public function getCountProductsReturnInRoadAttribute()
    {
        return SalesProduct::where('status_id', 5)->whereIn('id', SalesFinancesFromFilesPreload::where([
            ['file_id', $this->id],
        ])->whereNotNull('product_id')->pluck('product_id')->toArray())->count();
    }

    public function getCountExistAttribute()
    {
        $count = 0;
        foreach($this->preloadedFinances as $PreloadedFinance)
        {
            if($PreloadedFinance->exist) $count++;
        }
        return $count;
    }

    public function getCountQuantityNotEqualAttribute()
    {
        $count = 0;
        foreach($this->preloadedFinances as $PreloadedFinance)
        {
            $SaleProduct = $PreloadedFinance->SaleProduct;
            if($SaleProduct and ($SaleProduct->product_quantity !== $PreloadedFinance->product_quantity))
            {
                $count++;
            }
        }
        return $count;
    }

    public function getCountCompareCommissionNotEqualAttribute()
    {
        $count = 0;
        foreach($this->preloadedFinances as $PreloadedFinance)
        {
            $SaleProduct = $PreloadedFinance->SaleProduct;
            if($SaleProduct and (round($SaleProduct->commission_percent, 2) !== round($PreloadedFinance->compare_commission, 2)))
            {
                $count++;
            }
        }
        return $count;
    }

    public function getReturnCountCompareCommissionNotEqualAttribute()
    {
        $count = 0;
        foreach($this->preloadedFinancesReturns as $PreloadedFinance)
        {
            $SaleProduct = $PreloadedFinance->SaleProduct;
            if($SaleProduct and (round($SaleProduct->commission_percent, 2) !== round($PreloadedFinance->compare_commission, 2)))
            {
                $count++;
            }
        }
        return $count;
    }

    public function getCountCompareCommissionNotEqualFiveAttribute()
    {
        $count = 0;
        foreach($this->preloadedFinances as $PreloadedFinance)
        {
            if($PreloadedFinance->SaleCompareCommissionClass)
            {
                $count++;
            }
        }
        return $count;
    }

    public function getReturnCountCompareCommissionNotEqualFiveAttribute()
    {
        $count = 0;
        foreach($this->preloadedFinancesReturns as $PreloadedFinance)
        {
            if($PreloadedFinance->SaleCompareCommissionClass)
            {
                $count++;
            }
        }
        return $count;
    }

    public function getCountRowDoubleAttribute()
    {
        return $this->preloadedFinances->where('row_double', 1)->count();
    }


    public function getCompareCommissionAttribute()
    {
        return $this->preloadedFinances->where('compare_commission', '!=', NULL)->count() > 0;
    }

    public function getCompareOzonPrice1Attribute()
    {
        return $this->preloadedFinances->where('ozon_price_1', '!=', 0)->count() > 0;
    }
    public function getCompareOzonPrice2Attribute()
    {
        return $this->preloadedFinances->where('ozon_price_2', '!=', 0)->count() > 0;
    }

    public function getTotalOzonPrice1Attribute()
    {
        $total = 0;
        foreach($this->preloadedFinances as $PreloadedFinance)
        {
            $total += $PreloadedFinance->ozon_price_1 * $PreloadedFinance->product_quantity;
        }
        return $total;
    }

    public function getTotalOzonPrice2Attribute()
    {
        $total = 0;
        foreach($this->preloadedFinances as $PreloadedFinance)
        {
            $total += $PreloadedFinance->ozon_price_2 * $PreloadedFinance->product_quantity;
        }
        return $total;
    }
    public function getReturnTotalOzonPrice2Attribute()
    {
        $total = 0;
        foreach($this->preloadedFinancesReturns as $PreloadedFinance)
        {
            $total += $PreloadedFinance->ozon_price_2 * $PreloadedFinance->product_quantity;
        }
        return $total;
    }


    public function getServicesAttribute()
    {
        return $this->allTypePreloadedFinances->pluck('service_id')->toArray();
    }


    public function financesFromFile(): HasMany
    {
        return $this
            ->hasMany('App\Eloquent\Sales\SalesFinancesFromFile', 'file_id', 'id');
    }


    public function getTitleAttribute()
    {
        $originalName = '';
        if($this->original_name)
            $originalName = $this->original_name;

        if($this->wb_report_id and $WbReport = WbReport::where('id', $this->wb_report_id)->first())
            $originalName = $WbReport->Title;

        return $originalName;

    }
}
