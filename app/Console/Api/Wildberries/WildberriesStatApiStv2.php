<?php

namespace App\Console\Api\Wildberries;

use App\Console\Api\Api;
use App\Eloquent\Order\Order;
use App\Models\Products;
use Carbon\Carbon;

class WildberriesStatApiStv2 extends WildberriesStatApi
{
    public $systemId = 1772; // Wildberries
    public $shopId = 1772;

    public function __construct()
    {
        parent::__construct();
    }

}
