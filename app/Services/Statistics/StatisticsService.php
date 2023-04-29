<?php

namespace App\Services\Statistics;

use App\Repository\Statistics\StatisticsRepository;
use App\Services\Base\BaseModelService;
use App\Models\Orders;
use Carbon\Carbon;
use App\Eloquent\Sales\Sale;
use App\Eloquent\Order\OrdersTypeShop;
use DB;

class StatisticsService extends BaseModelService
{
    public $interval = 'day'; // интервал просчёта
    public $shopId = 'all'; //id магазина
    public $unit = 'ryb'; //рубли\штуки
    public $dateStartSales = ''; //дата начала продаж
    public $dateEndSales = ''; //дата окончания продаж
    public $union = false; //объединение
    public $type = 'sales'; //тип
    public $builder = ''; //Builder
    public $arrayCollectionBtDate = [];

    public $arrayCollectionByDatePreparations = [];
    public $arrayCollectionByDateForCalculatePercentages = [];
    public $arrayCollectionBtDatePercentagesRefunds = [];

    public $checkedSp = true;
    public $percentages = false;
    public $checkedSelfPurchase = true;
    public $checkedStatusCancel = true;
    public $article;

    /**
     * @see BaseModelService
     */
    protected static $repositoryClass = StatisticsRepository::class;

    public function setUnionTrue() 
    {
        $this->union = true;
        $this->repository->setUnionTrue();
    }

    /**
     * Флаг возврат в процентах (только ВОЗВРАТЫ)
     * 
     * @return void
     */
    public function setRefundsPercentagesTrue(): void
    {
        $this->percentages = true;
    }

    /**
     * Установка параметров
     * 
     * @param string $interval
     * @param mixed $shopId
     * @param string $unit
     * @param string|null $dateStartSales
     * @param string|null $dateEndSales
     * 
     * @return void
     */
    public function setProperties(
        string $interval, 
        $shopId, 
        string $unit, 
        ?string $dateStartSales, 
        ?string $dateEndSales, 
        string $type,
        string $checkedSp,
        string $checkedSelfPurchase,
        string $checkedStatusCancel,
        ?string $article
    ): void
    {
        $this->interval = $interval;
        $this->shopId = $shopId;
        $this->unit = $unit;
        $this->dateStartSales = $dateStartSales;
        $this->dateEndSales = $dateEndSales;
        $this->type = $type;
        $this->article = $article;

        if($checkedSp == 'false') {
            $this->checkedSp = false;
        }

        if($checkedSelfPurchase == 'false') {
            $this->checkedSelfPurchase = false;
        }

        if($checkedStatusCancel == 'false') {
            $this->checkedStatusCancel = false;
        }

        $this->repository->setProperties(
            $this->interval, 
            $this->shopId, 
            $this->unit, 
            $this->dateStartSales, 
            $this->dateEndSales,
            $this->type,
            $this->checkedSp,
            $this->checkedSelfPurchase,
            $this->checkedStatusCancel,
            $this->article
        );

        $this->repository->initBuilderOrder();
    }

    /**
     * Генерация статистики
     * 
     * @return array
     */
    public function getInfoStaticsOrder(): array
    {
        $this->builder = $this->repository->getBuilder();

        $this->arrayCollectionBtDate = $this->builder
            ->get()
            ->groupBy(function($data) {
                if ($this->interval == 'day') {
                    return Carbon::parse($data->date_sale)->format('Y-m-d');
                }

                if ($this->interval == 'month') {
                    return Carbon::parse($data->date_sale)->format('Y-m');;
                }

                if ($this->interval == 'year') {
                    return Carbon::parse($data->date_sale)->format('Y');
                }

                if ($this->interval == 'week') {
                    return Carbon::parse($data->date_sale)->format('Y-W');
                }
            });

        if ($this->unit == 'mrg') {
            $mrgArray = DB::connection('tech')
                ->table('calculation_mrg_sales')
                ->select('sale_id', 'mrg_sale')
                ->pluck('mrg_sale', 'sale_id')
                ->toArray();
        }

        if ($this->percentages == true) {
            $this->getInfoByCalculatePercentagesRefunds();
        }

        foreach ($this->arrayCollectionBtDate as $date) {
            $firstItem = $date->first();
            if ($this->interval == 'day') {
                $dateMarket = Carbon::parse($firstItem->date_sale)->format('Y-m-d');
            }

            if ($this->interval == 'month') {
                $dateMarket = Carbon::parse($firstItem->date_sale)->format('Y-m');
            }

            if ($this->interval == 'year') {
                $dateMarket = Carbon::parse($firstItem->date_sale)->format('Y');
            }

            if ($this->interval == 'week') {
                $dateMarket = Carbon::parse($firstItem->date_sale)->format('Y-W');
            }

            $productPrice = 0;
            $productPurchasePrice = 0;
            $productMrg = 0;
            $saleCommission = 0;
            $incomesValue = 0;
            $costsValue = 0;
            $saleIncomesValue = 0;
            $count = 0;
            //$count = $date->count();
            $marginValue = [];
            $idSales = [];

            foreach ($date as $item) {
                $productPrice += $item->product_price * $item->product_quantity;
                $productPurchasePrice += $item->purchase_price * $item->product_quantity;

                if (in_array($item->id, $idSales) == false) {
                    $idSales[] = $item->id;
                    $count = $count + 1;
                }

                if ($this->unit == 'mrg') {
                    /* 
                        $model = Sale::where('id', $item->id)->first();
                        $marginValue[$item->id][] = $model->marginValue;
                    */

                    if (array_key_exists($item->id, $mrgArray) == true) {
                        $marginValue[$item->id][] = $mrgArray[$item->id];
                    }
                    else{
                        $model = Sale::where('id', $item->id)->first();
                        $marginValue[$item->id][] = $model->marginValue;
                    }
                }
            }

            if ($this->percentages == false) {
                if ($this->unit == 'ryb') {
                    $this->arrayCollectionByDatePreparation[] = [
                        'date' => (string) $dateMarket,
                        'value' => $productPrice
                    ];
                }


                if ($this->unit == 'count') {
                    $this->arrayCollectionByDatePreparation[] = [
                        'date' => (string) $dateMarket,
                        'value' => $count
                    ];
                }
    
                if ($this->unit == 'ryb-purchase') {
                    $this->arrayCollectionByDatePreparation[] = [
                        'date' => (string) $dateMarket,
                        'value' => $productPurchasePrice
                    ];
                }
    
                if ($this->unit == 'mrg') {
                    foreach($marginValue as $item) {
                        $productMrg += $item[0];
                    }
    
                    $this->arrayCollectionByDatePreparation[] = [
                        'date' => (string) $dateMarket,
                        'value' => $productMrg
                    ];
                }
            }else {
                if ($this->unit == 'ryb') {
                    $this->arrayCollectionByDatePreparation[] = [
                        'date' => (string) $dateMarket,
                        'value' => array_key_exists($dateMarket, $this->arrayCollectionByDateForCalculatePercentages) ? round(($productPrice*100)/($this->arrayCollectionByDateForCalculatePercentages[$dateMarket] + $productPrice), 2) : 0
                    ];
                }
    
                if ($this->unit == 'count') {
                    $this->arrayCollectionByDatePreparation[] = [
                        'date' => (string) $dateMarket,
                        'value' => array_key_exists($dateMarket, $this->arrayCollectionByDateForCalculatePercentages) ? round(($count*100)/($this->arrayCollectionByDateForCalculatePercentages[$dateMarket] + $count), 2) : 0
                    ];
                }
    
                if ($this->unit == 'ryb-purchase') {
                    $this->arrayCollectionByDatePreparation[] = [
                        'date' => (string) $dateMarket,
                        'value' => array_key_exists($dateMarket, $this->arrayCollectionByDateForCalculatePercentages) ? round(($productPurchasePrice*100)/($this->arrayCollectionByDateForCalculatePercentages[$dateMarket] + $productPurchasePrice), 2) : 0
                    ];
                }
    
                if ($this->unit == 'mrg') {
                    foreach($marginValue as $item) {
                        $productMrg += $item[0];
                    }
    
                    $this->arrayCollectionByDatePreparation[] = [
                        'date' => (string) $dateMarket,
                        'value' => array_key_exists($dateMarket, $this->arrayCollectionByDateForCalculatePercentages) ? round(($productMrg*100)/($this->arrayCollectionByDateForCalculatePercentages[$dateMarket] + $productMrg), 2) : 0 
                    ];
                }
            }

        }

        return empty($this->arrayCollectionByDatePreparation) == true ? [] : array_reverse($this->arrayCollectionByDatePreparation);
    }


    /**
     * Генерация статистики по продажам для таблицы
     * 
     * @return array
     */
    public function getInfoStaticsSalesByDateForTable(): array
    {
        $this->builder = $this->repository->getBuilder();
        $this->arrayCollectionBtDate = $this->builder->get();

        $shops = OrdersTypeShop::select('id', 'name')
            ->where('filter_order', '!=', 0)
            ->orderBy('filter_order')
            ->pluck('name', 'id')
            ->toArray();

        $statuses = DB::table('sales_products_statuses')->select('id', 'name')
            ->pluck('name', 'id')
            ->toArray();

        $arrayUniqueIdSales = [];

        foreach ($this->arrayCollectionBtDate as $item) {
            if (in_array($item->id, $arrayUniqueIdSales) == true) {
                continue;
            }

            $arrayUniqueIdSales[] = $item->id;

            $idSalesSystem = $item->system_order_id == null ? $item->id : $item->system_order_id;
            $status = array_key_exists($item->status_id, $statuses) == true ? $statuses[$item->status_id] : 'Статус не найден';

            $this->arrayCollectionByDatePreparation[] = [
                '<a class="btn btn-primary" href="https://crmdollmagic.ru/sales/edit/' . $item->id . '" target="_blank">' . $idSalesSystem . '</a>
                <p id="' . $item->id . '" hidden>' . $idSalesSystem . '</p>
                <i class="bi bi-clipboard copy" onclick="copyToClipboard(\'#' . $item->id . '\')"></i>
                ',
                $item->date_sale,
                $status,
                $shops[$item->type_shop_id],
            ];
        }

        return empty($this->arrayCollectionByDatePreparation) == true ? [] : array_reverse($this->arrayCollectionByDatePreparation);
    }

    /**
     * Массив для просчёта процентов
     * 
     * @return array
     */
    public function getInfoByCalculatePercentagesRefunds(): array
    {
        $this->repository->setProperties(
            $this->interval, 
            $this->shopId, 
            $this->unit, 
            $this->dateStartSales, 
            $this->dateEndSales,
            'sales',
            $this->checkedSp,
            $this->checkedSelfPurchase,
            $this->checkedStatusCancel,
            $this->article
        );

        $this->repository->initBuilderOrder();

        $this->builder = $this->repository->getBuilder();

        $this->arrayCollectionBtDatePercentagesRefunds = $this->builder
            ->get()
            ->groupBy(function($data) {
                if ($this->interval == 'day') {
                    return Carbon::parse($data->date_sale)->format('Y-m-d');
                }

                if ($this->interval == 'month') {
                    return Carbon::parse($data->date_sale)->format('Y-m');;
                }

                if ($this->interval == 'year') {
                    return Carbon::parse($data->date_sale)->format('Y');
                }

                if ($this->interval == 'week') {
                    return Carbon::parse($data->date_sale)->format('Y-W');
                }
            });

        if ($this->unit == 'mrg') {
            $mrgArray = DB::connection('tech')
                ->table('calculation_mrg_sales')
                ->select('sale_id', 'mrg_sale')
                ->pluck('mrg_sale', 'sale_id')
                ->toArray();
        }

        foreach ($this->arrayCollectionBtDatePercentagesRefunds as $date) {
            $firstItem = $date->first();
            if ($this->interval == 'day') {
                $dateMarket = Carbon::parse($firstItem->date_sale)->format('Y-m-d');
            }

            if ($this->interval == 'month') {
                $dateMarket = Carbon::parse($firstItem->date_sale)->format('Y-m');
            }

            if ($this->interval == 'year') {
                $dateMarket = Carbon::parse($firstItem->date_sale)->format('Y');
            }

            if ($this->interval == 'week') {
                $dateMarket = Carbon::parse($firstItem->date_sale)->format('Y-W');
            }

            $productPrice = 0;
            $productPurchasePrice = 0;
            $productMrg = 0;
            $saleCommission = 0;
            $incomesValue = 0;
            $costsValue = 0;
            $saleIncomesValue = 0;
            $count = 0;
            //$count = $date->count();
            $marginValue = [];
            $idSales = [];

            foreach ($date as $item) {
                $productPrice += $item->product_price * $item->product_quantity;
                $productPurchasePrice += $item->purchase_price * $item->product_quantity;

                if (in_array($item->id, $idSales) == false) {
                    $idSales[] = $item->id;
                    $count = $count + 1;
                }

                if ($this->unit == 'mrg') {
                    /* 
                        $model = Sale::where('id', $item->id)->first();
                        $marginValue[$item->id][] = $model->marginValue;
                    */

                    if (array_key_exists($item->id, $mrgArray) == true) {
                        $marginValue[$item->id][] = $mrgArray[$item->id];
                    }
                    else{
                        $model = Sale::where('id', $item->id)->first();
                        $marginValue[$item->id][] = $model->marginValue;
                    }
                }
            }


            if ($this->unit == 'ryb') {
                $this->arrayCollectionByDateForCalculatePercentages[$dateMarket] = $productPrice;
            }

            if ($this->unit == 'count') {
                $this->arrayCollectionByDateForCalculatePercentages[$dateMarket] = $count;
            }

            if ($this->unit == 'ryb-purchase') {
                $this->arrayCollectionByDateForCalculatePercentages[$dateMarket] = $productPurchasePrice;
            }

            if ($this->unit == 'mrg') {
                foreach($marginValue as $item) {
                    $productMrg += $item[0];
                }

                $this->arrayCollectionByDateForCalculatePercentages[$dateMarket] = $productMrg;
            }

        }

        return empty($this->arrayCollectionByDateForCalculatePercentages) == true ? [] : $this->arrayCollectionByDateForCalculatePercentages;
    }
}
