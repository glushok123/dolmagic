<?php

namespace App\Console\Api\Yandex;

use App\Console\Api\YandexApi3;

class YandexApiDBS2 extends YandexApi3
{
    // DBS2
    public $systemId = 174;
    public $shopId = 174;
    public $appSecret = 'ed88b6a3b9c41537e27a38612b55bfd7';
    public $campaignId = '23745160';
    public $host = "https://api.partner.market.yandex.ru/v2";

    //https://oauth.yandex.ru/authorize?response_type=token&client_id=f7594d2d0c6c43818c4eee7fb1932865

    public function __construct()
    {
        parent::__construct();

        $this->headers = array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: OAuth oauth_token="AQAAAABglG8bAAaoXLWuKqiWtEtNr2S7igNNX3Q", oauth_client_id="f7594d2d0c6c43818c4eee7fb1932865"'
        );
    }
}
