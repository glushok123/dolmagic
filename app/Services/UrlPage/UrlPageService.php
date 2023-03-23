<?php

namespace App\Services\UrlPage;

use App\Repository\UrlPage\UrlPageRepository;
use App\Services\Base\BaseModelService;
use App\Models\UrlPage;
use App\Models\Configuration;

class UrlPageService extends BaseModelService
{
    public $urlLength = 10;
    public $string = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public $url;
    public $generateUrl = [];
    public $model;
    public $modelParentPayLevel;
    public $countPayLevel = 0;
    public $payLevel;

    /**
     * @see BaseModelService
     */
    protected static $repositoryClass = UrlPageRepository::class;

    /**
     * Инициализация параметров
     * 
     * @param string $url
     * 
     * @return void
     */
    public function setProperties(string $url): void
    {
        $this->url = $url;
        $this->repository->setProperties($url);
        $this->model = UrlPage::where('url', $url)->first();
        $this->getParentByPayment();
    }

    /**
     * генерация уникального url
     * 
     * @param int $count
     * @param string $parentUrl
     * 
     * @return void
     */
    public function generateUniqueUrl(int $count = 1, string $parentUrl = ''): void
    {
        $countGenerateUrl = 0;
        $this->generateUrl = [];

        while ($countGenerateUrl != $count) {
            $generate = false;

            while ($generate == false) {
                $url = substr(str_shuffle($this->string), 0, $this->urlLength);

                if (UrlPage::where('url', $url)->exists() == false) {
                    $model = new UrlPage();
                    $model->url = $url;
                    $model->parent_url = $parentUrl;
                    $model->save();

                    $generate = true;
                    $this->generateUrl[] = [$url, new \DateTime()];
                    $countGenerateUrl = $countGenerateUrl + 1;
                }
            }
        }
    }

    /**
     * Список сгенерированных url
     * 
     * @return array
     */
    public function getUniqueUrl(): array
    {
        return $this->generateUrl;
    }
    /**
     * Телефон активного пользователя
     * 
     * @return string|null
     */
    public function getPhone(): ?string
    {
        return $this->model->phone;
    }

    /**
     * Ссылка активного пользователя
     * 
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Общее количество ссылок
     * 
     * @return int
     */
    public function getCountUrl(): int
    {
        return $this->repository->getCountUrl();
    }

    /**
     * Общее количество активированных ссылок
     * 
     * @return int
     */
    public function getCountActiveUrl(): int
    {
        return $this->repository->getCountActiveUrl();
    }

    /**
     * Количество участников, в ветках которых полностью активирован уровень PAYLEVEL
     * 
     * @return int
     */
    public function getCountActivePayLevelUrl(): int
    {
        return $this->url;
    }

    public function getChildren()
    {
        return UrlPage::where('parent_url', $this->url)->get();
    }

    /**
     * Получение модели пользователя уровня payLevel для оплаты
     * 
     * @return void
     */
    public function getParentByPayment(): void
    {
        $payLevel = (int) Configuration::where('name', 'PAYLEVEL')->value('value');
        $payLevel = $payLevel - 1;
        $count = 1;
        $url_parent = $this->model->parent_url;

        while ($count != $payLevel) {
            if (UrlPage::where('url', $url_parent)->exists() == true) {
                $urlParent = UrlPage::where('url', $url_parent)->value('parent_url');

                if ($urlParent == null) {
                    break;
                }

                $url_parent = $urlParent;
            }
            else{
                break;
            }
            $count = $count + 1;
        }

        $this->modelParentPayLevel = UrlPage::where('url', $url_parent)->first();
    }

    /**
     * Получение телефона пользователя уровня payLevel для оплаты
     * 
     * @return string|null
     */
    public function getPhoneParentByPayment(): ?string
    {
        return $this->modelParentPayLevel->phone;
    }

    /**
     * Получение телефона пользователя уровня payLevel для оплаты
     * 
     * @return string|null
     */
    public function getPaymentMethodParentByPayment(): ?string
    {
        return $this->modelParentPayLevel->payment_method;
    }

    /**
     * Количество участников, в ветках которых полностью активирован уровень PAYLEVEL
     * 
     * @return int
     */
    public function getCountPayLevel(): int
    {
        $this->countPayLevel = 0;
        $this->payLevel = (int) Configuration::where('name', 'PAYLEVEL')->value('value');

        UrlPage::chunk(100, function($items) {
            foreach ($items as $item) {
                $count = 0;
                $level = $item;
                while (true) {
                    if ($level->time_payment != null) {
                        $level = UrlPage::where('parent_url', $level->url)->first();
                        $count = $count + 1;
                        if ($count == $this->payLevel) {
                            $this->countPayLevel = $this->countPayLevel + 1;
                            break;
                        }
                    }
                    else{
                        break;
                    }
                }
            }
        });

        return $this->countPayLevel;
    }
}