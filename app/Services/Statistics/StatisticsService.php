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

    public $checkedSp = true;
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

            $count = $date->count();
            $marginValue = [];

            foreach ($date as $item) {
                $productPrice += $item->product_price * $item->product_quantity;
                $productPurchasePrice += $item->purchase_price * $item->product_quantity;

                if ($this->unit == 'mrg') {
                    $model = Sale::where('id', $item->id)->first();
                    $marginValue[$item->id][] = $model->marginValue;
                }
            }

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

            $this->arrayCollectionByDatePreparation[] = [
                '<a class="btn btn-primary" href="https://crmdollmagic.ru/sales/edit/' . $item->id . '" target="_blank">' . $item->id . '</a>',
                $item->date_sale,
                $statuses[$item->status_id],
                $shops[$item->type_shop_id],
            ];
        }

        return empty($this->arrayCollectionByDatePreparation) == true ? [] : array_reverse($this->arrayCollectionByDatePreparation);
    }
}
