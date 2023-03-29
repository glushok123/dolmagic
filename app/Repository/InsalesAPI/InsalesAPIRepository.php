<?php

namespace App\Repository\InsalesAPI;

use App\Repository\Base\BaseModelRepository;
use App\Models\InsalesAPI;

class InsalesAPIRepository extends BaseModelRepository
{
    protected static $entityClass = InsalesAPI::class;

    /**
     * Возвращает первичные категории
     *
     * @return UrlPage
     */
    public static function test()
    {
        dd('репозиторий');
    }
}