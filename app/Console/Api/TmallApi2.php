<?php

namespace App\Console\Api;

class TmallApi2 extends Api
{
    public $systemId = 8; // Aliexpress = 8
    public $shopId = 8;
    public $token = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzZWxsZXJfaWQiOjk4ODA2ODAxOSwidG9rZW5faWQiOjMzODB9.G0nIjHz2T62Z-Nj_H-ZOOtrvEpVjjIN6gsEA9XczCOKHM_0ckkvbY59dtkLI9zP3ofcgnGacctIZEN7Q7BP4R3EBon_DbHg_pbq_2W1xVzPrfTyuLwzludaNFovRwlZWrTjKr_WlAdUBjPPglERmQkmG3Tb15LWPQ2i7ZgH0Fj4aOXn-shTRSj-yoaSZJkxv5UnkMjZQDwNYQ3ecnOUjGO1yBSkKlR4WvjlMQFetDYH3G0odBjW5xPzbspfFq57jM2ERlk0WLdqAYgApByMWj_yzSxoR_hnRW0OgjQ_4Myo5FGBOrhU3WyjoKajPvgavCERxjnih18n6VWHdBOuy96mOQ3Dwq8s1XSqNZJ34h3e1HsdvHk7Dc5llF-itU0tRfXcvv-K_8rXOHUXihRbz6Qlzikj8QomHCcYyTe6GBGz_LsbVFt0aoStvaHC-J82DI0MZ1gH7H7MoyeP9O0eBvU3MtmXYG6LlsmlPnme8kluP5ViCFmStN5wv3y6eCyk0v8IUmLOvG_0zCv2YbFK4Ffe2sMFwQkKEQCxUo0XFsmYWi-zuQaUf-waRG7PcM80tLgLfUW6Vzy00-fQw6vjxADJKHw_zPvSxTS3Oa44D-a108N6Soty5OR1JEIonCk-kC_SQTZkMPrvQtTv2A4x2kEdkOYx9IODy9LyzTuNDrJ4';
    public $host = 'https://openapi.aliexpress.ru'; // Host
    // https://business.aliexpress.ru/docs/api-token





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

        $this->headers = array(
            'x-auth-token: '.$this->token,
            'Content-Type: application/json'
        );
    }

    public function getOrdersList()
    {
        $res = $this->makeRequest(
            'POST',
            '/seller-api/v1/order/get-order-list',
            [
                'date_start' => '2022-08-22T00:00:00Z',
                'page_size' => 10,
                'page' => 1
            ]
        );

        dd($res);
    }

    public function getStocks()
    {
        $res = $this->makeRequest(
            'POST',
            '/api/v1/stocks',
            [
                'stocks' =>
                [
                    ['sku' => '12000026369847728']
                ]
            ]
        );

        dd($res);
    }

    public function getProducts()
    {
        $res = $this->makeRequest(
            'POST',
            '/api/v1/scroll-short-product-by-filter',
            [
                'limit' => 50
            ]
        );

        dd($res);
    }






    /*
     * curl --location --request GET 'https://openapi.aliexpress.ru/api/v1/method-group/method' \
--header 'x-auth-token: token'
     */

}
