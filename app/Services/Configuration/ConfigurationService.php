<?php

namespace App\Services\Configuration;

use App\Repository\Configuration\ConfigurationRepository;
use App\Services\Base\BaseModelService;
use App\Models\Configuration;

class ConfigurationService extends BaseModelService
{
    /**
     * @see BaseModelService
     */
    protected static $repositoryClass = ConfigurationRepository::class;

    /**
     * количество ссылок, генерирующихся после активации аккаунта для передачи своим доверенным
     * 
     * @return string
     */
    public function getKOLSYL(): ?string
    {
        return Configuration::where('name', 'KOLSYL')->value('value');
    }

    /**
     * на каком уровне своей ветки каждый получает бонус от взносов
     * 
     * @return string
     */
    public function getPAYLEVEL(): ?string
    {
        return Configuration::where('name', 'PAYLEVEL')->value('value');
    }

    /**
     * сумма, которую платит каждый участник за себя и за каждого доверенного, если соглашается участвовать в проекте
     * 
     * @return string
     */
    public function getPAYSUM(): ?string
    {
        return Configuration::where('name', 'PAYSUM')->value('value');
    }

    /**
     * сумма, которую каждый участник обязуется перевести в конце первого этапа Проекта
     * 
     * @return string
     */
    public function getPAYRESERVE(): ?string
    {
        return Configuration::where('name', 'PAYRESERVE')->value('value');
    }

    public function getSum(): ?int
    {
        $kolsyl = (int) Configuration::where('name', 'KOLSYL')->value('value');
        $pyasum = (int) Configuration::where('name', 'PAYSUM')->value('value');

        return (int) ($kolsyl + 1)*$pyasum;
    }
}