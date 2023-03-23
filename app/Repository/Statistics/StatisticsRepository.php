<?php

namespace App\Repository\Statistics;

use App\Repository\Base\BaseModelRepository;
use App\Models\StatisticsOrder;
use App\Eloquent\Sales\Sale;
use DB;
use Carbon\Carbon;

class StatisticsRepository extends BaseModelRepository
{
    protected static $entityClass = Sale::class;

    public $interval = 'day'; // интервал просчёта
    public $shopId = 'all'; //id магазина
    public $unit = 'ryb'; //рубли\штуки
    public $dateStartSales = ''; //дата начала продаж
    public $dateEndSales = ''; //дата окончания продаж
    public $union = false; //объединение
    public $type = 'sales'; //тип
    public $builder = ''; //Builder

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
    public function setProperties(string $interval, $shopId, string $unit, ?string $dateStartSales, ?string $dateEndSales, string $type): void
    {
        $this->interval = $interval;
        $this->shopId = $shopId;
        $this->unit = $unit;
        $this->dateStartSales = $dateStartSales;
        $this->dateEndSales = $dateEndSales;
        $this->type = $type;
    }

    public function setUnionTrue() : void
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
    public function initBuilderOrder(): void
    {
        $this->builder = DB::table('sales');
        $this->addSelectBuilderOrder();
        $this->addWhereBuilderOrder();
        $this->addOrderByBuilderOrder();
        $this->addLimitBuilderOrder();
        $this->addJoinBuilderOrder();
    }

    /**
     * Необходимые поля
     * 
     * @return void
     */
    public function addSelectBuilderOrder(): void
    {
        $this->builder = $this->builder
            ->select(
                'sales.id', 
                'sales.type_shop_id', 
                'sales.date_sale', 
                'sales_products.product_price',
                'sales_products.purchase_price',
                'sales.margin_value',
                'sales_products.product_quantity',
                'sales_products.commission_value',
                'sales_products.status_id',
            );
    }

    /**
     * Условия
     * 
     * @return void
     */
    public function addWhereBuilderOrder(): void
    {
        $this->builder = $this->builder
            ->where('sales.self_redemption', '<>', 1)
            ->where('sales.sp_sale', '<>', 1)
            ->where('sales.state', '>', -1);

        if ($this->type == 'sales') {
            $this->builder = $this->builder
                ->where('sales_products.status_id', '<>', 3)
                ->where('sales_products.status_id', '<>', 4)
                ->where('sales_products.status_id', '<>', 5)
                ->where('sales_products.status_id', '<>', 6)
                ->where('sales_products.status_id', '<>', 9)
                ->where('sales_products.status_id', '<>', 10);
        }

        if ($this->type == 'refunds') {
            $this->builder = $this->builder
                ->whereIn('sales_products.status_id', [4, 5, 9, 10]);
        }

        if ($this->union == true) {
            $this->builder = $this->builder
                ->whereIn('orders_type_shops.id', $this->shopId);

        }else{
            if ($this->shopId != 'all') {
                $this->builder = $this->builder
                    ->where('orders_type_shops.id', '=', $this->shopId);
            }
        }

        if ($this->dateStartSales != null) {
            $this->builder = $this->builder
                ->where('sales.date_sale', '>=', $this->dateStartSales);
        }

        if ($this->dateEndSales != null) {
            $dateEndSales = Carbon::parse($this->dateEndSales);
            $dateEndSales->addDays(1);

            $this->builder = $this->builder
                ->where('sales.date_sale', '<=', $dateEndSales );
        }
    }

    /**
     * Сортировка
     * 
     * @return void
     */
    public function addOrderByBuilderOrder(): void
    {
        $this->builder = $this->builder
            ->orderBy('sales.date_sale', 'DESC');
    }
    
    /**
     * лимит запроса
     * 
     * @return void
     */
    public function addLimitBuilderOrder(): void
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
        ->leftJoin('sales_products', 'sales.id', '=', 'sales_products.sale_id')
        ->leftJoin('orders_type_shops', 'sales.type_shop_id', '=', 'orders_type_shops.id');
    }
}