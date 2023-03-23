<?php

namespace App\Repository\Test;

use App\Repository\Base\BaseModelRepository;
use App\Models\Test;

class TestRepository extends BaseModelRepository
{
    protected static $entityClass = Test::class;

    /**
     * Возвращает первичные категории
     *
     * @return Category
     */
    public static function test()
    {
        dd('репозиторий');
    }
}