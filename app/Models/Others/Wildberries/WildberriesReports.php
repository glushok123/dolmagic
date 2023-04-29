<?php

namespace App\Models\Others\Wildberries;

use App\Console\Api\Wildberries\WildberriesStatApi;
use App\Eloquent\Other\WB\WbReport;
use App\Eloquent\Other\WB\WbReportsData;
use App\Eloquent\Other\WB\WbReportsDocTypeName;
use App\Eloquent\Other\WB\WbReportsOperationName;
use App\Eloquent\Products\Product;
use App\Eloquent\Products\TypeShopProduct;
use App\Eloquent\Sales\Sale;
use App\Eloquent\Sales\SalesCost;
use App\Eloquent\Sales\SalesFinancesFile;
use App\Eloquent\Sales\SalesFinancesFromFile;
use App\Eloquent\Sales\SalesFinancesFromFilesPreload;
use App\Eloquent\Sales\SalesProduct;
use App\Models\Model;
use App\Models\Others\Yandex\YandexFBY;
use App\Models\Products;
use App\Models\Sales\SalesFinances;
use App\Models\Shops\Shops;
use App\Models\Users\Notifications;
use App\Models\Users\Users;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class WildberriesReports extends Model
{

    public static function getReportId($realizationreport_id, $shopId)
    {
        $WbReport = WbReport::firstOrCreate(
            [
                'realizationreport_id' =>  $realizationreport_id,
                'shop_id' => $shopId,
            ]
        );

        return $WbReport->id;
    }

    public static function getReportDocName($title)
    {
        $WbReportsDocTypeName = WbReportsDocTypeName::firstOrCreate(
            ['title' =>  trim($title)]
        );

        return $WbReportsDocTypeName->id;
    }

    public static function getReportOperation($ReportRow)
    {
        $title = trim($ReportRow->supplier_oper_name);
        switch($title)
        {
            case 'Логистика':
                    if($ReportRow->return_amount)
                        $title = 'Логистика (Обратная)';
                break;
        }
        return WbReportsOperationName::firstOrCreate(
            ['title' =>  trim($title)],
        );
    }

    public static function getReportOperationName($title)
    {
        return self::getReportOperation($title)->id;
    }

    public static function saleMatching(&$WbReportsData, $Report, $shopId)
    {
        $groupShopIds = Shops::getAllShopByMain($shopId);
        $WbReportsData->sale_create = 0;
        $WbReportsData->sale_id = 0;
        $WbReportsData->sale_found_by = '';

        $reportRid = false;
        $reportSrid = false;

        if(isset($Report->rid) and !empty($Report->rid))
            $reportRid = $Report->rid;
        if(isset($Report->srid) and !empty($Report->srid))
            $reportSrid = $Report->srid;


        // first is SRID

        if($reportSrid) // find in sale by srid
        {
            if($Sale = Sale::whereIn('type_shop_id', $groupShopIds)->where(function($q) use ($reportSrid)
            {
                $q
                    ->where('rid', $reportSrid)
                    ->orWhere('srid', $reportSrid);
            })->first())
            {
                $WbReportsData->sale_id = $Sale->id;
                $WbReportsData->sale_found_by = 'Совпадение srid из отчёта с rid/srid продажи';
                return true;
            }
        }

        if($reportSrid)
        {
            // find by report data
            if($prevWbReportsData = WbReportsData::where('shop_id', $shopId)
                ->where('srid', $reportSrid)
                ->where(function($q)
                {
                    $q
                        ->where('sale_id', '!=', 0) // where has sale
                        ->orWhere('sale_create', '!=', 0);
                })
                ->orderBy('sale_id', 'DESC')
                ->first()
            )
            {
                // there is get another creating from report
                if($prevWbReportsData->sale_id)
                {
                    $countAnother = WbReportsData
                        ::where('sale_id', $prevWbReportsData->sale_id)
                        ->where('srid', '!=', '0')
                        ->groupBy('srid')
                        ->get()
                        ->count();

                    if($countAnother > 1)
                    {
                        $WbReportsData->sale_found_by = 'Исключение из продажи из-за разных srid (1)';
                        $WbReportsData->sale_create = WbReportsData::max('sale_create') + 1;
                        return false;
                    }else
                    {
                        $WbReportsData->sale_id = $prevWbReportsData->sale_id;
                        $WbReportsData->sale_found_by = 'Совпадение по srid с другой строкой отчёта (1)';
                        return true;
                    }
                }else
                {
                    $countAnother = WbReportsData
                        ::where('sale_create', $prevWbReportsData->sale_create)
                        ->where('srid', '!=', '0')
                        ->groupBy('srid')
                        ->get()
                        ->count();

                    if($countAnother > 1)
                    {
                        $WbReportsData->sale_found_by = 'Исключение из продажи из-за разных srid (2)';
                        $WbReportsData->sale_create = WbReportsData::max('sale_create') + 1;
                        return false;
                    }else
                    {
                        $WbReportsData->sale_create = $prevWbReportsData->sale_create;
                        $WbReportsData->sale_found_by = 'Совпадение по srid с другой строкой отчёта (2)';
                        return true;
                    }
                }
            }
        }

        // second is RID

        if($reportRid) // find in sale by rid
        {
            if($Sale = Sale::whereIn('type_shop_id', $groupShopIds)->where(function($q) use ($reportRid)
            {
                $q
                    ->where('rid', $reportRid)
                    ->orWhere('srid', $reportRid);
            })->first())
            {
                $WbReportsData->sale_id = $Sale->id;
                $WbReportsData->sale_found_by = 'Совпадение rid из отчёта с rid/srid продажи';
                return true;
            }
        }



        if($reportRid) // find in reports data
        {
            if($prevWbReportsData = WbReportsData::where('shop_id', $shopId)
                ->where('rid', $reportRid)
                ->where(function($q)
                {
                    $q
                        ->where('sale_id', '!=', 0) // where has sale
                        ->orWhere('sale_create', '!=', 0); // where sale create
                })
                ->orderBy('sale_id', 'DESC')
                ->first()
            )
            {
                // there is get another creating from report
                if($prevWbReportsData->sale_id) // if has sale
                {
                    $countRid = WbReportsData
                        ::where('sale_id', $prevWbReportsData->sale_id)
                        ->where('rid', '!=', '0')
                        ->groupBy('rid')
                        ->get()
                        ->count();

                    if($countRid > 1)
                    {
                        $WbReportsData->sale_found_by = 'Исключение из продажи из-за разных rid (1)';
                        $WbReportsData->sale_create = WbReportsData::max('sale_create') + 1;
                        return false;
                    }else
                    {
                        $WbReportsData->sale_id = $prevWbReportsData->sale_id;
                        $WbReportsData->sale_found_by = 'Совпадение по rid с другой строкой отчёта (1)';
                        return true;
                    }
                }else
                {
                    $countRid = WbReportsData
                        ::where('sale_create', $prevWbReportsData->sale_create)
                        ->where('rid', '!=', '0')
                        ->groupBy('rid')
                        ->get()
                        ->count();

                    if($countRid > 1)
                    {
                        $WbReportsData->sale_found_by = 'Исключение из продажи из-за разных rid (2)';
                        $WbReportsData->sale_create = WbReportsData::max('sale_create') + 1;
                        return false;
                    }else
                    {
                        $WbReportsData->sale_create = $prevWbReportsData->sale_create;
                        $WbReportsData->sale_found_by = 'Совпадение по rid с другой строкой отчёта (2)';
                        return true;
                    }
                }
            }
        }

        // USE next only if it doesn't have srid or rid
        if(!$reportSrid and !$reportRid)
        {

            if(isset($Report->sticker_id) and !empty($Report->sticker_id))
            {
                if($Sale = Sale::whereIn('type_shop_id', $groupShopIds)->where(function ($q) use ($Report)
                {
                    $q->where('sticker_id', $Report->sticker_id);
                })->first())
                {
                    $WbReportsData->sale_id = $Sale->id;
                    $WbReportsData->sale_found_by = 'sticker_id';
                    return true;
                }
            }

            if(isset($Report->shk_id) and !empty($Report->shk_id))
            {
                if($Sale = Sale::whereIn('type_shop_id', $groupShopIds)->where(function ($q) use ($Report)
                {
                    $q->where('sticker_id', $Report->shk_id);
                })->first())
                {
                    $WbReportsData->sale_id = $Sale->id;
                    $WbReportsData->sale_found_by = 'sticker_id - shk_id';
                    return true;
                }
            }

            // if sticker is doesn't found.
            if(isset($Report->shk_id) and !empty($Report->shk_id))
            {
                if($Sale = Sale::whereIn('type_shop_id', $groupShopIds)->where(function ($q) use ($Report)
                {
                    $q
                        ->where(function ($q)
                        {
                            $q->whereNull('sticker_id')
                                ->orWhere('sticker_id', '');
                        })
                        ->where('shk_id', $Report->shk_id);
                })->orderBy('created_at', 'DESC')->first())
                {
                    $WbReportsData->sale_id = $Sale->id;
                    $WbReportsData->sale_found_by = 'shk_id';
                    return true;
                }
            }

            // need to create new sale. Looking for srid and shk_id


            if(isset($Report->srid) and !empty($Report->srid))
            {
                if($prevWbReportsData = WbReportsData::where('shop_id', $shopId)->where(function($q)
                {
                    $q->whereNull('sticker_id')
                        ->orWhere('sticker_id', '');
                })->where('srid', $Report->srid)->where(function($q)
                {
                    $q->where('sale_create', '!=', 0);
                })->first()) {
                    // there is get another creating from report
                    $WbReportsData->sale_create = $prevWbReportsData->sale_create;
                    $WbReportsData->sale_found_by = 'srid(2)';
                    return true;
                }
            }

            if(isset($Report->shk_id) and !empty($Report->shk_id))
            {
                if($prevWbReportsData = WbReportsData::where('shop_id', $shopId)->where(function($q)
                {
                    $q->whereNull('sticker_id')
                        ->orWhere('sticker_id', '');
                })->where('shk_id', $Report->shk_id)->where(function($q)
                {
                    $q->where('sale_create', '!=', 0);
                })->first()) {
                    // there is get another creating from report
                    $WbReportsData->sale_create = $prevWbReportsData->sale_create;
                    $WbReportsData->sale_found_by = 'shk_id(2)';
                    return true;
                }
            }

            if(isset($Report->srid) and !empty($Report->srid))
            {
                if($prevWbReportsData = WbReportsData::where('shop_id', $shopId)
                    ->where('srid', $Report->srid)->first()
                )
                {
                    // there is get another creating from report
                    $WbReportsData->sale_create = $prevWbReportsData->sale_create;
                    $WbReportsData->sale_found_by = 'srid(3)';
                    return true;
                }
            }
        }



        // there is creating new sale 100%
        $WbReportsData->sale_create = WbReportsData::max('sale_create') + 1;

        if(
            (!isset($Report->srid) or !$Report->srid)
            and (!isset($Report->sticker_id) or !$Report->sticker_id)
            and (!isset($Report->shk_id) or !$Report->shk_id)
        )
        {
            $WbReportsData->sale_found_by = 'error 1';
        }

        return false;
    }

    public static function saveReports($shopId, $reportId = false)
    {
        $WildberriesStatApi = Wildberries::getStatApi($shopId);

        if($reportId)
        {
            $WbReport = WbReport::where('id', $reportId)->first();
            $fromDate = Carbon::parse($WbReport->FromDate)->subDays(30)->format('Y-m-d');
            $toDate = Carbon::parse($WbReport->FromDate)->addDays(30)->format('Y-m-d');
        }else
        {
            $fromDate = Carbon::now()->subDays(14)->format('Y-m-d');
            $toDate = Carbon::now()->format('Y-m-d');
        }

        $reports = $WildberriesStatApi->getSupplierReportDetailByPeriod($fromDate, $toDate);
        if(!$reports)
        {
            dump('Ошибка - нет ответа по отчётам');
            return false;
        }
        if(isset($reports->errors) and $reports->errors)
        {
            dump('Данные WB недоступны, повторите позднее (хоть сразу)');
            dd($reports);
        }

        $countReports = count($reports);
        foreach($reports as $key => $Report)
        {
            dump("$key / $countReports");

            if(isset($WbReport) and ($WbReport->realizationreport_id !== $Report->realizationreport_id))
            {
                continue;
            }

            $WbReportsData = WbReportsData::firstOrNew([
                'realizationreport_id' => $Report->realizationreport_id,
                'rrd_id' => $Report->rrd_id,
                'shop_id' => $shopId,
            ]);

            $wbReportId = self::getReportId($Report->realizationreport_id, $shopId);
            $WbReportsData->wb_report_id = $wbReportId;

            $WbReportsData->shop_id = $shopId; // is main shop id

            $WbReportsData->nm_id = $Report->nm_id;
            $WbReportsData->sa_name = $Report->sa_name;

            if($Product = Products::getProductWithPostfix($Report->sa_name, $shopId))
                $WbReportsData->product_id = $Product->id;

            $WbReportsData->barcode = $Report->barcode;
            $WbReportsData->wb_reports_doc_type_name_id = self::getReportDocName($Report->doc_type_name);
            $WbReportsData->quantity = $Report->quantity?:1;

            $WbReportsData->order_date = Carbon::parse($Report->order_dt);
            $WbReportsData->sale_date = Carbon::parse($Report->sale_dt);
            $WbReportsData->rr_date = Carbon::parse($Report->rr_dt);

            $WbReportsData->shk_id = $Report->shk_id;

            $WbReportsData->delivery_rub = $Report->delivery_rub;
            $WbReportsData->rid = $Report->rid;
            $WbReportsData->srid = $Report->srid;
            $WbReportsData->sticker_id = $Report->sticker_id;


            self::saleMatching($WbReportsData, $Report, $shopId); // sale comparing

            $WbReportsData->sale_percent = $Report->sale_percent;
            $WbReportsData->retail_amount = $Report->retail_amount;
            $WbReportsData->retail_price = $Report->retail_price;
            $WbReportsData->product_discount_for_report = $Report->product_discount_for_report;

            $WbReportsData->penalty = $Report->penalty;
            $WbReportsData->delivery_amount = $Report->delivery_amount;
            $WbReportsData->return_amount = $Report->return_amount;

            $ReportOperation = self::getReportOperation($Report);
            $WbReportsData->wb_reports_operation_name_id = $ReportOperation->id;
            $WbReportsData->retail_price_withdisc_rub = $ReportOperation->sign * $Report->retail_price_withdisc_rub;
            $WbReportsData->ppvz_for_pay = $ReportOperation->sign * $Report->ppvz_for_pay;

            if($WbReportsData->OperationName->product_price_set and ($WbReportsData->retail_price_withdisc_rub > 0))
            {
                $WbReportsData->calc_commission = round(($WbReportsData->retail_price_withdisc_rub - $WbReportsData->ppvz_for_pay), 2);
                $WbReportsData->calc_commission_percent = round($WbReportsData->calc_commission / $WbReportsData->retail_price_withdisc_rub * 100, 2);
            }

            //newest
            if(isset($Report->additional_payment))
                $WbReportsData->additional_payment = $Report->additional_payment;

            if(isset($Report->bonus_type_name))
                $WbReportsData->bonus_type_name = $Report->bonus_type_name;

            $WbReportsData->save();
        }

        dump('Загружено ' . count($reports));
    }



    public static function createFinanceFile($WbReport, $shopId)
    {
        $userId = auth()->user()->id??0;
        $SalesFinancesFile = new SalesFinancesFile;
        $SalesFinancesFile->shop_id = $shopId;
        $SalesFinancesFile->wb_report_id = $WbReport->id;
        $SalesFinancesFile->user_id = $userId;
        if($SalesFinancesFile->save())
        {
            return $SalesFinancesFile;
        };

        return false;
    }

    public static function getIncomePriceByOperationName($WbReportData)
    {
        if(
            isset($WbReportData->OperationName)
            and $WbReportData->OperationName->income_field
            and isset($WbReportData->{$WbReportData->OperationName->income_field})
        )
        {
            return $WbReportData->{$WbReportData->OperationName->income_field};
        }else
        {
            return false;
        }
    }

    public static function getPriceByOperationName($WbReportData)
    {
        if($WbReportData->OperationName->field === 'check_all')
        {
            if((float) $WbReportData->penalty) return $WbReportData->penalty;
            if((float) $WbReportData->delivery_rub) return $WbReportData->delivery_rub;
            if((float) $WbReportData->return_amount) return $WbReportData->return_amount;
            if((float) $WbReportData->ppvz_for_pay) return $WbReportData->ppvz_for_pay;
            if((float) $WbReportData->retail_price_withdisc_rub) return $WbReportData->retail_price_withdisc_rub;
        }else
        {
            return $WbReportData->{$WbReportData->OperationName->field};
        }
    }

    public static function saveReportFromApiFile($wbReportId, $shopId)
    {
        if(!$WbReport = WbReport::where('id', $wbReportId)->first()) return false;
        if(!$FinanceFile = self::createFinanceFile($WbReport, $shopId)) return false;


        foreach($WbReport->data as $WbReportData)
        {
            $isReturn = ($WbReportData->wb_reports_doc_type_name_id === 2);

            $serviceId = YandexFBY::getServiceIdByName($WbReportData->operationName->title);
            $financeId = YandexFBY::getFinanceIdByName($WbReportData->docTypeName->title);

            $price = self::getPriceByOperationName($WbReportData);

            $q = [
                'is_return' => $isReturn,

                'shop_id' => $shopId,
                'file_id' => $FinanceFile->id,
                'finance_id' => $financeId,
                'service_id' => $serviceId,
                'price' => $price,
                'date' => $WbReportData->order_date,
                'finance_number' => $WbReport->realizationreport_id,
                'product_id' => $WbReportData->product_id,
                'product_sku' => $WbReportData->Sku,
                'product_quantity' => $WbReportData->{$WbReportData->operationName->quantity_field},

                'compare_commission' => $WbReportData->calc_commission_percent,
                'compare_commission_value' => $WbReportData->calc_commission,

                'wb_data_id' => $WbReportData->id,
            ];

            if($incomePrice = self::getIncomePriceByOperationName($WbReportData))
                $q['wb_income'] = $incomePrice;

            if($WbReportData->sale_id)
            {
                $q['sale_id'] = $WbReportData->sale_id;
            }else
            {
                $q['sale_create'] = $WbReportData->sale_create;
            }

            $SalesFinancesFromFilesPreload = SalesFinancesFromFilesPreload::firstOrNew($q);
            $SalesFinancesFromFilesPreload->save();
        }

        return true;
    }

    public static function createCostFromWbData($PreloadedFinances)
    {
        if(
            $WbData = WbReportsData::where('id', $PreloadedFinances->wb_data_id)->first()
            and
            isset($PreloadedFinances->service->cost_id)
            and
            $PreloadedFinances->service->cost_id
        )
        {
            $SalesCost = new SalesCost;

            $SalesCost->user_id = Users::getCurrentUserId();
            $SalesCost->last_user_id = Users::getCurrentUserId();

            $SalesCost->sale_id = $PreloadedFinances->sale_id;
            $SalesCost->cost_id = $PreloadedFinances->service->cost_id;
            $SalesCost->comment = "Создан через Финансы 3.0: загрузка отчётов (Отчёт $WbData->realizationreport_id)";
            $SalesCost->value = $PreloadedFinances->price;
            $SalesCost->value_type_id = 1;
            $SalesCost->flow_type_id = 1;
            $SalesCost->wb_data_id = $PreloadedFinances->wb_data_id;
            if($SalesCost->save())
            {
                $costId = (int) $SalesCost->cost_id;
                if($costId === 54) // return delivery
                {
                    if($Sale = Sale::where('id', $SalesCost->sale_id)->first())
                    {
                        Notifications::notifyWBFinanceV3Return($Sale, 'reports_finance_v3');
                    }
                }
            }
        }
    }

    public static function wbSetSalesStatusSell($salesIds)
    {
        $statusId = 2; // SELL
        $errorText = '';
        $countError = 0;
        $countSuccess = 0;
        $countProcessed = 0;
        $countWithoutStatus = 0;

        foreach($salesIds as $saleId)
        {
            if(Sale::where('id', $saleId)->first())
            {
                if(
                    $Sale = Sale::where('id', $saleId)
                    ->whereHas('financesFromFile', function($q)
                    {
                        $q->whereHas('service', function($q)
                        {
                            $q->whereNull('cost_id');
                        });
                    })
                    ->first()
                )
                {
                    $salesProducts = SalesProduct::where([
                        ['sale_id', '=', $saleId]
                    ])->whereIn('status_id', [1, 8]) // ONLY FOR NEW/DELIVERED ITEMS
                    ->get();

                    if(count($salesProducts) > 0) {
                        foreach($salesProducts as $SaleProduct) {
                            $SaleProduct->status_id = $statusId;
                            $SaleProduct->product_price = $Sale->WBTotalFinancesFromFile;
                            $SaleProduct->commission_percent = round((float)$Sale->WBCommissionPercent, 2);
                            $SaleProduct->commission_value = round(
                                $SaleProduct->product_price * $SaleProduct->commission_percent / 100
                                , 2
                            );

                            if($SaleProduct->save()) {
                                $countSuccess++;
                            } else {
                                $countError++;
                                $errorText .= "Ошибка обновления продажи #$saleId продукт #$SaleProduct->id<br/>";
                            };
                        }
                    } else {
                        $countWithoutStatus++;
                    }
                }else
                {
                    $errorText .= "Не найдены ДОХОДЫ продажи #$saleId <br/>";
                    $countError++;
                }
            }else{
                $errorText .= "Не найдена продажа #$saleId <br/>";
                $countError++;
            }

            $countProcessed++;
        }

        return [
            'error' => (($countError > 0)?$errorText:false),
            'countError' => $countError,
            'countSuccess' => $countSuccess,
            'countProcessed' => $countProcessed,
            'countWithoutStatus' => $countWithoutStatus,
        ];
    }

    public static function getLogisticsCostIds(): array
    {
        return [57, 53, 54];
    }

    public static function getPenaltyCostIds(): array
    {
        return [55];
    }

    public static function getReconciledCostIds(): array
    {
        return array_merge(self::getLogisticsCostIds(), self::getPenaltyCostIds());
    }

    public static function wbSetSalesFinancesReconciled($salesIds)
    {
        $statusId = 2; // SELL
        $errorText = '';
        $countError = 0;
        $countSuccess = 0;
        $countProcessed = 0;
        $countWithoutStatus = 0;

        foreach($salesIds as $saleId)
        {
            if($Sale = Sale::where('id', $saleId)->first())
            {
                $salesFinancesFromFiles = SalesFinancesFromFile
                    ::with('service')
                    ->where('sale_id', $saleId)
                    ->whereHas('service', function($q)
                    {
                        $q->whereIn('cost_id', self::getReconciledCostIds());
                    })
                    ->get();

                if(count($salesFinancesFromFiles) === 0)
                {
                    $countWithoutStatus++;
                    continue;
                }

                $costIds = [];
                foreach($salesFinancesFromFiles as $SalesFinancesFromFile)
                {
                    if($SalesFinancesFromFile->service and $SalesFinancesFromFile->service->cost_id)
                        $costIds[] = $SalesFinancesFromFile->service->cost_id;
                }

                // JUST REMOVE AND CREATE NEW
                $salesCosts = SalesCost
                    ::where('sale_id', $Sale->id)
                    ->whereIn('cost_id', $costIds)
                    ->get();
                foreach($salesCosts as $SalesCost)
                {
                    $SalesCost->delete();
                }

                $wasPenalty = false;
                foreach($salesFinancesFromFiles as $SalesFinancesFromFile)
                {
                    $SalesCost = new SalesCost;
                    $SalesCost->sale_id = $Sale->id;
                    $SalesCost->cost_id = $SalesFinancesFromFile->service->cost_id??58;
                    $SalesCost->value = $SalesFinancesFromFile->price?:0;
                    $SalesCost->value_type_id = 1;
                    $SalesCost->flow_type_id = 1;
                    $SalesCost->user_id = auth()->user()->id??3;
                    $SalesCost->last_user_id = auth()->user()->id??3;
                    $SalesCost->comment = 'Перезагрузка через сверку Доходы WB 5.0';

                    if($SalesCost->save())
                    {
                        $SalesFinancesFromFile->reconciled = 1;
                        $SalesFinancesFromFile->save();

                        if($SalesCost->cost_id === 55) $wasPenalty = true;
                    }else
                    {
                        $errorText .= "Ошибка сохранения расхода перед сверкой #$saleId <br/>";
                        $countError++;
                    }
                }

                if(!$wasPenalty) // If wasn't penalty in finances - 55 costId
                {
                    $PenaltyCost = SalesCost::firstOrNew([
                        'sale_id' => $Sale->id,
                        'cost_id' => 55,
                    ]);
                    $PenaltyCost->value = 0;
                    $PenaltyCost->value_type_id = 1;
                    $PenaltyCost->flow_type_id = 1;
                    $PenaltyCost->user_id = auth()->user()->id??3;
                    $PenaltyCost->last_user_id = auth()->user()->id??3;
                    $PenaltyCost->comment = 'Корректировка через сверку Доходы WB 5.0';
                    $PenaltyCost->save();
                }

                SalesFinances::checkAndSaveSaleReconciled($Sale);
            }else{
                $errorText .= "Не найдена продажа #$saleId <br/>";
                $countError++;
            }

            $countProcessed++;
        }

        return [
            'error' => (($countError > 0)?$errorText:false),
            'countError' => $countError,
            'countSuccess' => $countSuccess,
            'countProcessed' => $countProcessed,
            'countWithoutStatus' => $countWithoutStatus,
        ];
    }

    public static function reportsUpdateNotLoaded($shopId)
    {
        //accepted
        $reportsIds = [];
        $wbReports = WbReport::where('shop_id', $shopId)->orderBy('id', 'ASC')->get();
        foreach($wbReports as $WbReport)
        {
            if(!$WbReport->FinancesLoaded)
            {
                $reportsIds[] = $WbReport->id;
            }
        }

        $reportsCount = count($reportsIds);

        foreach($reportsIds as $key => $reportId)
        {
            dump("$key / $reportsCount");
            self::reportsDataClearSales($reportId);
        }

        foreach($reportsIds as $key => $reportId)
        {
            dump("$key / $reportsCount");
            self::reportsDataUpdateSales($reportId);
        }

        dump('OK');
    }

    public static function reportsDataClearSales($reportId)
    {
        $Report = WbReport::where('id', $reportId)->firstOrFail();
        foreach($Report->data as $WbReportsData)
        {
            $WbReportsData->sale_create = 0;
            $WbReportsData->sale_id = 0;
            $WbReportsData->sale_found_by = '';
            $WbReportsData->save();
        }
    }

    public static function reportsDataUpdateSales($reportId)
    {
        $Report = WbReport::where('id', $reportId)->firstOrFail();
        $total = count($Report->data);
        foreach($Report->data as $key => $WbReportsData)
        {
            dump("$key / $total (reportsDataUpdateSales)");
            WildberriesReports::saleMatching($WbReportsData, $WbReportsData, $Report->shop_id);
            $WbReportsData->save();
        }
    }

    public static function updateAllReports()
    {
        $wildberriesReports = WbReport::orderBy('id', 'ASC')->get();
        $total = count($wildberriesReports);

        foreach($wildberriesReports as $key => $WbReport)
        {
            dump($key . ' / ' . $total);
            WildberriesReports::reportsDataUpdateSales($WbReport->id); // for update first time
            WildberriesReports::reportsDataUpdateSales($WbReport->id); // for update second time (srid(2))
        }
    }


    public static function checkDoubleDeliveryFinances($Sale, $SalesFinancesFromFile)
    {
        // service_id
        // 53 Логистика
        // 58 Логистика (Обратная)
        if(in_array($SalesFinancesFromFile->service_id, [53, 58]))
        {
            if(SalesFinancesFromFile
                ::where('sale_id', $SalesFinancesFromFile->sale_id)
                ->where('service_id', $SalesFinancesFromFile->service_id)
                ->count() > 1)
            {
                Notifications::notifyWBFinanceV3DoubleDelivery($Sale, 'reports_finance_v3');
            }
        }
    }

    public static function checkSalesIdBetweenReportAndFinances()
    {
        $salesFinancesFromFiles = SalesFinancesFromFile::whereIn('shop_id', Shops::getShopIdsByType('Wildberries'))->get();
        foreach($salesFinancesFromFiles as $SFFF)
        {
            if($WbData = $SFFF->wbData)
            {
                if($WbData->sale_id !== $SFFF->sale_id)
                {
                    dump('wbd '.$WbData->id.' '.$WbData->sale_id.' sff '.$SFFF->id.' '.$SFFF->sale_id);
                }
            }
        }
    }



    public static function getReportRow($shopId, $rrd_id)
    {
        $WildberriesStatApi = Wildberries::getStatApi($shopId);

        $WbData = WbReportsData::where('rrd_id', $rrd_id)->firstOrFail();
        $WbReport = WbReport::where('id', $WbData->wb_report_id)->first();
        $fromDate = Carbon::parse($WbReport->FromDate)->subDays(7)->format('Y-m-d');
        $toDate = Carbon::parse($WbReport->FromDate)->addDays(7)->format('Y-m-d');

        $reports = $WildberriesStatApi->getSupplierReportDetailByPeriod($fromDate, $toDate);
        foreach($reports as $Report)
        {
            if($Report->rrd_id === $rrd_id)
            {
                dd($Report);
            }
        }

        dd('Nothing found');
    }
}


