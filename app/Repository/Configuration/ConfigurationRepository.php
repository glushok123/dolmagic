<?php

namespace App\Repository\Configuration;

use App\Repository\Base\BaseModelRepository;
use App\Models\Configuration;

class ConfigurationRepository extends BaseModelRepository
{
    protected static $entityClass = Configuration::class;

    /**
     * Возвращает первичные категории
     *
     * @return Configuration
     */
    public static function test()
    {
        dd('репозиторий');
    }


}