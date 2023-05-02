<?php

namespace App\Console\Api;

class AliApi extends TmallApi
{
    public $systemId = 8; // Aliexpress = 8
    public $shopId = 8;
    public $appKey = 32238155;
    public $appSecret = 'db57990757b8495296b32a2d6d790555';
    public $sessionKey = '50000700f15DWP0qaZzKwjnuDrQ135b1ba0Dbccr4sVVjlgOGxmyw9i0Aoq21EOxF38';
    public $importUpPrice = 150;
    public $importUpPricePercent = 7;
    public $importUpPriceLimit = 1000; // this max price for up price
    public $tmallClient;

    // Session key = https://oauth.aliexpress.com/authorize?response_type=token&client_id=32238155&state=1212&view=web&sp=ae

    // other
    //public $freight_template_id = 1014643005; // "1014643005" - dostavka1
    public $freight_template_id = 24036288309; // "24036288309" - Шаблон FBS. Пункт приёма Почта России 1
    public $header_module_id = 1005000000265762;
    public $footer_module_id = 1005000000267249;

    public function  __construct()
    {
        parent::__construct();
    }

}
