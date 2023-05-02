<?php

namespace App\Console\Api\Wildberries;

use App\Console\Api\Api;
use App\Eloquent\Order\Order;
use App\Models\Products;
use Carbon\Carbon;

class WildberriesApi2 extends WildberriesApi
{
    public $systemId = 179; // Wildberries
    public $shopId = 179;

    public $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhY2Nlc3NJRCI6IjMwMzc3YzExLTM2NGEtNDViNy05NWE1LWI1ZWE3Nzc4NzIzNSJ9.7Ab1qALLz4ntvZ4zyzv2Lh2LcNX8nDTDi-1EcP8oDlI';
    public $host = 'https://suppliers-api.wildberries.ru';

    //public $warehouseId = 333066;
    public $warehouseId = 631160;

    public function __construct()
    {
        parent::__construct();
    }
}
