<?php

namespace App\Console\Api\Wildberries;

use App\Console\Api\Api;
use App\Eloquent\Order\Order;
use App\Models\Products;
use Carbon\Carbon;

class WildberriesStatApi extends Api
{
    public $systemId = 177; // Wildberries
    public $shopId = 177;

    public $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhY2Nlc3NJRCI6IjViMGE4NmE0LTRmYmYtNGI0NS05YmY2LWE4ZjdkZWM2Mjk4NSJ9.JkgOzEz92Jet5aDNfI1Mfp3XRnTSjgB5KYmUspXKybM';
    public $host = 'https://statistics-api.wildberries.ru';



    public function __construct()
    {
        parent::__construct();

        $this->СronOptions = $this->getCronOptions();
        $this->headers = array(
            'Content-Type: application/json',
            "Authorization: $this->token"
        );
    }

    public function getSupplierOrders($dateFrom)
    {
        $res = $this->makeRequest(
            'GET',
            "/api/v1/supplier/orders",
            [
                'dateFrom' => $dateFrom, // '2022-05-20T00:00:00.000Z',
                'flag' => 0,
                'key' => $this->token,
            ]
        );
        return $res??false;
    }


    public function getSupplierStocks()
    {
        $res = $this->makeRequest(
            'GET',
            "/api/v1/supplier/stocks",
            [
                'dateFrom' => '2022-06-01T00:00:00.000Z',
                'key' => $this->token,
            ]
        );

       return $res;
    }

    // ?limit=1000&rrdid=0&dateto=2020-06-30

    public function getSupplierReportDetailByPeriod($dateFrom, $dateTo)
    {
        $res = $this->makeRequest(
            'GET',
            "/api/v1/supplier/reportDetailByPeriod",
            [
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'limit' => 100000,
                'rrdid' => 0,
                'key' => $this->token,
            ]
        );

        return $res;
    }

    public function supplierSales($dateFrom)
    {

        $res = $this->makeRequest(
            'GET',
            '/api/v1/supplier/sales',
            [
                'key' => $this->token,
                'dateFrom' => $dateFrom
            ]
        );
        return is_array($res)?$res:[];
    }




    public function supplierStocksV1()
    {
        $res = $this->makeRequest(
            'GET',
            '/api/v1/supplier/stocks',
            [
                'dateFrom' => Carbon::now('Europe/Moscow')->subDay()->format('Y-m-d')
            ]
        );

        return $res;
    }




}
