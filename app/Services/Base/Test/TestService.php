<?php

namespace App\Services\Test;

use App\Repository\Test\TestRepository;
use App\Services\Base\BaseModelService;

class TestService extends BaseModelService
{
    /**
     * @see BaseModelService
     */
    protected static $repositoryClass = TestRepository::class;

    public function test()
    {
        $this->repository->test();
        dd('сервис');
    }
}