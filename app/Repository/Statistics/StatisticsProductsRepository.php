<?php

namespace App\Repository\Statistics;

use App\Repository\Base\BaseModelRepository;
use App\Models\StatisticsOrder;
use DB;

class StatisticsProductsRepository extends BaseModelRepository
{
    protected static $entityClass = StatisticsOrder::class;

    public $interval = 'day'; // интервал просчёта
    public $warehousesId = 'all'; //id магазина
    public $unit = 'ryb'; //рубли\штуки
    public $dateStart = ''; //дата начала продаж
    public $dateEnd = ''; //дата окончания продаж
    public $union = false; //объединение
    public $builder = ''; //Builder
    public $article;

    public function setProperties(string $interval, $warehousesId, string $unit, ?string $dateStart, ?string $dateEnd, ?string $article): void
    {
        $this->interval = $interval;
        $this->warehousesId = $warehousesId;
        $this->unit = $unit;
        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
        $this->article = $article;
    }

    public function setUnionTrue() 
    {
        $this->union = true;
    }

    public function getBuilder()
    {
        return $this->builder;
    }

    /**
     * Инициализация запроса
     * 
     * @return void
     */
    public function initBuilder()
    {
        $this->builder = DB::table('warehouses_stocks');
        $this->addSelectBuilder();
        $this->addWhereBuilder();
        $this->addOrderByBuilder();
        $this->addLimitBuilder();
        $this->addJoinBuilderOrder();
    }

    /**
     * Необходимые поля
     * 
     * @return void
     */
    public function addSelectBuilder(): void
    {
        $this->builder = $this->builder
            ->select(
                'warehouses_stocks.id', 
                'warehouses_stocks.product_id', 
                'warehouses_stocks.warehouse_id', 
                'warehouses_stocks.available', 
                'warehouses_stocks.reserved', 
                'warehouses_stocks.save_date', 
                'warehouses_stocks.arriving', 
                'products.sku',
            );
    }

    /**
     * Условия
     * 
     * @return void
     */
    public function addWhereBuilder(): void
    {
        if($this->article != null) {
            $this->builder = $this->builder
                ->where('products.sku', $this->article);
        }

        if ($this->union == true) {
            $this->builder = $this->builder
                ->whereIn('warehouses_stocks.warehouse_id', $this->warehousesId);

        }else{
            if ($this->warehousesId != 'all') {
                $this->builder = $this->builder
                    ->where('warehouses_stocks.warehouse_id', '=', $this->warehousesId);
            }
        }

        if ($this->dateStart != null) {
            $this->builder = $this->builder
                ->where('warehouses_stocks.save_date', '>=', $this->dateStart);
        }

        if ($this->dateEnd != null) {
            $dateEndSales = Carbon::parse($this->dateEnd);
            $dateEndSales->addDays(1);

            $this->builder = $this->builder
                ->where('warehouses_stocks.save_date', '<=', $dateEndSales);
        }
    }

    /**
     * Сортировка
     * 
     * @return void
     */
    public function addOrderByBuilder(): void
    {
        $this->builder = $this->builder
            ->orderBy('warehouses_stocks.save_date', 'DESC');
    }

    /**
     * лимит запроса
     * 
     * @return void
     */
    public function addLimitBuilder(): void
    {
        $this->builder = $this->builder
            ->limit(100000);
    }

    /**
     * Добавление таблиц
     * 
     * @return void
     */
    public function addJoinBuilderOrder(): void
    {
        $this->builder = $this->builder
            ->leftJoin('products', 'warehouses_stocks.product_id', '=', 'products.id');

    }
}