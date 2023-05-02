<?php

namespace App\Console\Api\Yandex;

use App\Console\Api\YandexApiFBS;

class YandexApiFBS2 extends YandexApiFBS
{
    // FBS2
    // https://yandex.ru/dev/market/partner-marketplace/doc/dg/reference/post-campaigns-id-offer-mapping-entries-updates.html
    public $systemId = 176;
    public $shopId = 176;
    public $appSecret = 'ed88b6a3b9c41537e27a38612b55bfd7';
    public $campaignId = '23745684';
    public $host = 'https://api.partner.market.yandex.ru/v2/campaigns/23745684';

    // 11-23316905
    // DF00000161800E10

    //https://yandex.ru/dev/id/doc/dg/oauth/tasks/get-oauth-token.html
    //https://oauth.yandex.ru/verification_code?response_type=token&client_id=f7594d2d0c6c43818c4eee7fb1932865
    //https://oauth.yandex.ru/authorize?response_type=token&client_id=f7594d2d0c6c43818c4eee7fb1932865

    public function __construct()
    {
        parent::__construct();

        $this->headers = array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: OAuth oauth_token="AQAAAAAW7htAAAaoXBrJizC4HER6vh9VhACRw2I", oauth_client_id="f7594d2d0c6c43818c4eee7fb1932865"'
        );
    }
}
