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
    public $checkedSp = true;
    public $checkedSelfPurchase = true;
    public $checkedStatusCancel = true;
    public $article;

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
        bool $checkedSp,
        bool $checkedSelfPurchase,
        bool $checkedStatusCancel,
        ?string $article
    ): void
    {
        $this->interval = $interval;
        $this->shopId = $shopId;
        $this->unit = $unit;
        $this->dateStartSales = $dateStartSales;
        $this->dateEndSales = $dateEndSales;
        $this->type = $type;
        $this->checkedSp = $checkedSp;
        $this->checkedSelfPurchase = $checkedSelfPurchase;
        $this->checkedStatusCancel = $checkedStatusCancel;
        $this->article = $article;
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
                'products.sku',
                'orders.system_order_id',
            );
    }

    /**
     * Условия
     * 
     * @return void
     */
    public function addWhereBuilderOrder(): void
    {
        if ($this->article != null) {
            $this->builder = $this->builder
                ->where('products.sku', $this->article);
        }
        if ($this->checkedSp == true) {
            $this->builder = $this->builder
                ->where('sales.sp_sale', '<>', 1);
        }

        if ($this->checkedSelfPurchase == true) {
            $this->builder = $this->builder
                ->where('sales.self_redemption', '<>', 1);
        }

        $this->builder = $this->builder
            ->where('sales.state', '>', -1);

        if ($this->type == 'sales') {
            if ($this->unit != 'mrg'){
                if ($this->checkedStatusCancel == true) {
                    $this->builder = $this->builder
                        ->where('sales_products.status_id', '<>', 3);//Отмена
                }

                $this->builder = $this->builder
                    ->where('sales_products.status_id', '<>', 4)//Возврат
                    ->where('sales_products.status_id', '<>', 5)//Возврат в пути
                    ->where('sales_products.status_id', '<>', 6)//Отмена озон (для FBO)
                    ->where('sales_products.status_id', '<>', 9)//Возврат после получения
                    ->where('sales_products.status_id', '<>', 10);//Возврат в пути после получения
            }
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
        ->leftJoin('products', 'sales_products.product_id', '=', 'products.id')
        ->leftJoin('orders_type_shops', 'sales.type_shop_id', '=', 'orders_type_shops.id')
        ->leftJoin('orders', 'sales.order_id', '=', 'orders.id');
    }
}