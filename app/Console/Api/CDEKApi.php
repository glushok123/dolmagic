<?php

namespace App\Console\Api;

use App\Eloquent\Deliveries\DeliveryCdekRegion;
use App\Models\Products;
use Carbon\Carbon;

class CDEKApi extends Api
{
    public $client_id = 'MUaKVZVJToJucqR9e9quUThoqI9t8wpa';
    public $client_secret= 'uJT1AatMucxumoo7KtB6hKEOHiDu8bwU';
    public $host = 'https://api.cdek.ru/v2';

    public function __construct()
    {
        parent::__construct();


        $this->headers = array(
            'Content-Type: application/json; charset=utf-8'
        );

        $accessToken = $this->getAccessToken();

        $this->headers = array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Bearer '.$accessToken,
        );
    }

    public function getAccessToken()
    {
        $event = "/oauth/token?grant_type=client_credentials&client_id=$this->client_id&client_secret=$this->client_secret";
        $res = $this->makeRequest(
            'POST',
            $event
        );
        return $res->access_token;
    }


    public function getDeliveryPoint($code)
    {
        $deliveryPoints = $this->getDeliveryPoints();
        foreach($deliveryPoints as $DeliveryPoint)
        {
            if($DeliveryPoint->code === $code)
                return $DeliveryPoint;
        }

        return false;
    }

    public function getDeliveryPoints($filter2 = false)
    {
        $filter = [
            'type' => 'PVZ',
            'country_code' => 'RU',
        ];
        if($filter2) $filter = array_merge($filter, $filter2);

        $event = "/deliverypoints";
        $res = $this->makeRequest(
            'GET',
            $event,
            $filter
        );
        return $res;
    }



    public function getLocationCities($region_code = false)
    {
        $filter = [
            'country_codes' => 'RU',
            'region_code' => '13'
        ];
        $event = "/location/cities";
        $res = $this->makeRequest(
            'GET',
            $event,
            $filter
        );

        foreach($res as $City)
        {
            if($City->city === 'Красноярск') dd($City);
        }

        dd($res);
    }
    public function getLocationRegions($region_code = false)
    {
        $filter = [
            'country_codes' => 'RU',
            'region_code' => '13'
        ];
        $event = "/location/regions";
        $res = $this->makeRequest(
            'GET',
            $event,
            $filter
        );

        dd($res);

        die();
        foreach($res as $Res)
        {

            $DeliveryCdekRegion = DeliveryCdekRegion::firstOrNew(['region_code' => $Res->region_code]);
            $DeliveryCdekRegion->region_name = $Res->region;
            $DeliveryCdekRegion->save();
        }
        dd('123');
        dd($res);
        return $res;
    }



    public function getOutletDeliveryInfo($Outlet, $packages = false)
    {
        $event = "/calculator/tariff";
        $req = [
            'tariff_code' => 136,
            'from_location' => [
                'code' => 439 // STV
            ],
            'to_location' => [
                'code' => $Outlet->location->city_code
            ],
            'packages' =>
            [
                'weight' => 100,
                'length' => 27,
                'width' => 10,
                'height' => 32
            ]
        ];
        $res = $this->makeRequest(
            'POST',
            $event,
            $req
        );
        return $res;
    }

    public function getTariffList($toCode)
    {
        $event = "/calculator/tarifflist";
        $req = [
            'tariff_code' => 136,
            'from_location' => [
                'code' => 439 // STV
            ],
            'to_location' => [
                'code' => $toCode
            ],
            'packages' =>
                [
                    'weight' => 100,
                    'length' => 27,
                    'width' => 10,
                    'height' => 32
                ]
        ];
        $res = $this->makeRequest(
            'POST',
            $event,
            $req
        );
        return $res;
    }

    public function getOutletDeliveryInfoV2($Outlet, $tariffCodes = [234, 136])
    {
        // 234 = Экономичная посылка склад-склад
        // 136 = Посылка склад-склад

        $event = "/calculator/tariff";

        foreach($tariffCodes as $tariffCode)
        {
            $req = [
                'tariff_code' => $tariffCode,
                'from_location' => [
                    'code' => 439 // STV
                ],
                'to_location' => [
                    'code' => $Outlet->location->city_code
                ],
                'packages' =>
                [
                    'weight' => 100,
                    'length' => 27,
                    'width' => 10,
                    'height' => 32
                ],
            ];
            $res = $this->makeRequest(
                'POST',
                $event,
                $req
            );

            if(!isset($res->errors) and isset($res->period_min))
            {
                $res->tariff_code = $tariffCode;
                return $res;
            }else
            {
                //var_dump('error', $res);
            }
        }

        $res = new \stdClass();
        $res->error = true;
        return $res;
    }

}
