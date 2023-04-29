<?php

namespace App\Repository\SbermegamarketAPI;

use App\Repository\Base\BaseModelRepository;
use App\Models\SbermegamarketAPI;

class SbermegamarketAPIRepository extends BaseModelRepository
{
    protected static $entityClass = SbermegamarketAPI::class;

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