<?php

namespace App\Console\Api\Wildberries;

use App\Console\Api\Api;
use App\Eloquent\Order\Order;
use App\Models\Products;
use Carbon\Carbon;

class WildberriesStatApi2 extends WildberriesStatApi
{
    public $systemId = 179; // Wildberries
    public $shopId = 179;


    //public $token = 'M2RiMGRlZWUtMjY0NC00NDY2LThlNDYtYjQ0NWYxYTIwNjM0'; // x64 key M2RiMGRlZWUtMjY0NC00NDY2LThlNDYtYjQ0NWYxYTIwNjM0
    //public $host = 'https://suppliers-stats.wildberries.ru';

    public $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhY2Nlc3NJRCI6IjRmOTIxYWY3LTFhYTItNDQzMi1hYjk1LWNmOGJlZjQ0OWQzMyJ9.NtV-tWnZX4NgJPMKKvMgnnE6NGDyoaOdRA7dkPZ7pyA';
    public $host = 'https://statistics-api.wildberries.ru';

    //
    //



    public function __construct()
    {
        parent::__construct();
    }

}
