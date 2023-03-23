<?php

namespace App\Repository\UrlPage;

use App\Repository\Base\BaseModelRepository;
use App\Models\UrlPage;

class UrlPageRepository extends BaseModelRepository
{
    protected static $entityClass = UrlPage::class;
    public $url;

    public function setProperties(string $url)
    {
        $this->url = $url;
    }

    /**
     * Возвращает первичные категории
     *
     * @return UrlPage
     */
    public static function test()
    {
        dd('репозиторий');
    }

    /**
     * Общее количество ссылок
     * 
     * @return int
     */
    public static function getCountUrl(): int
    {
        return UrlPage::count();
    }

    /**
     * Общее количество активированных ссылок
     * 
     * @return int
     */
    public static function getCountActiveUrl(): int
    {
        return UrlPage::where('time_active', '<>', null)->count();
    }
}