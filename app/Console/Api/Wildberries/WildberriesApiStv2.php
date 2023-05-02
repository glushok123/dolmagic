<?php

namespace App\Console\Api\Wildberries;

use App\Console\Api\Api;
use App\Eloquent\Order\Order;
use App\Models\Products;
use Carbon\Carbon;

class WildberriesApiStv2 extends WildberriesApi
{
    public $systemId = 1772;
    public $shopId = 1772;
    public $warehouseId = 724941;

    public function __construct()
    {
        parent::__construct();
    }
}
