<?php

namespace App\Services\Statistics;

use App\Repository\Statistics\StatisticsProductsRepository;
use App\Services\Base\BaseModelService;
use App\Models\Orders;
use Carbon\Carbon;
use DB;

class StatisticsProductsService extends BaseModelService
{
    public $interval = 'day'; // интервал просчёта
    public $warehousesId = 'all'; //id магазина
    public $unit = 'ryb'; //рубли\штуки
    public $dateStart = ''; //дата начала продаж
    public $dateEnd = ''; //дата окончания продаж
    public $union = false; //объединение
    public $builder = ''; //Builder

    public $arrayCollectionByDate = [];
    public $arrayCollectionByDatePreparations = [];
    public $article;

    /**
     * @see BaseModelService
     */
    protected static $repositoryClass = StatisticsProductsRepository::class;

    public function setUnionTrue() 
    {
        $this->union = true;
        $this->repository->setUnionTrue();
    }

    /**
     * Установка параметров
     * 
     * @param string $interval
     * @param mixed $warehousesId
     * @param string $unit
     * @param string|null $dateStart
     * @param string|null $dateEnd
     * 
     * @return void
     */
    public function setProperties(string $interval, $warehousesId, string $unit, ?string $dateStart, ?string $dateEnd, ?string $article): void
    {
        $this->interval = $interval;
        $this->warehousesId = $warehousesId;
        $this->unit = $unit;
        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
        $this->article = $article;

        $this->repository->setProperties(
            $this->interval, 
            $this->warehousesId, 
            $this->unit, 
            $this->dateStart, 
            $this->dateEnd,
            $this->article
        );
        $this->repository->initBuilder();
    }

    /**
     * Генерация статистики
     * 
     * @return array
     */
    public function getInfoStaticsProducts(): array
    {
        $this->builder = $this->repository->getBuilder();

        $this->arrayCollectionByDate = $this->builder
            ->get()
            ->groupBy(function($data) {
                if ($this->interval == 'day') {
                    return Carbon::parse($data->save_date)->format('Y-m-d');
                }

                if ($this->interval == 'month') {
                    return Carbon::parse($data->save_date)->format('Y-m');;
                }

                if ($this->interval == 'year') {
                    return Carbon::parse($data->save_date)->format('Y');
                }

                if ($this->interval == 'week') {
                    return Carbon::parse($data->save_date)->format('Y-W');
                }
            });

        foreach($this->arrayCollectionByDate as $date) {
            $firstItem = $date->first();

            if ($this->interval == 'day') {
                $dateMarket = Carbon::parse($firstItem->save_date)->format('Y-m-d');
            }

            if ($this->interval == 'month') {
                $dateMarket = Carbon::parse($firstItem->save_date)->format('Y-m');
            }

            if ($this->interval == 'year') {
                $dateMarket = Carbon::parse($firstItem->save_date)->format('Y');
            }

            if ($this->interval == 'week') {
                $dateMarket = Carbon::parse($firstItem->save_date)->format('Y-W');
            }

            $countAvailable = 0;

            foreach($date as $item) {
                $countAvailable += $item->available;
            }

            if ($this->unit == 'count') {
                $this->arrayCollectionByDatePreparation[] = [
                    'date' => (string) $dateMarket,
                    'value' => (int) $countAvailable
                ];
            }
        }

        return empty($this->arrayCollectionByDatePreparation) == true ? [] : array_reverse($this->arrayCollectionByDatePreparation);
    }
}