<?php

namespace App\Console\Api;

use App\Models\Products;
use Carbon\Carbon;

class TaxcomApi extends Api
{
    public $systemId = 5; // Taxcom = 5
    public $shopId = 7;
    public $token = '592D0450-B602-43DD-A256-06CCD72856A2'; // Integrator-ID
    public $login = '440294@mail.ru';
    public $pass = 'Der348843'; // Password
    public $host = 'api-lk-ofd.taxcom.ru'; // Host
    public $timezone = 'Europe/Moscow';
    public $sessionToken = false; // auto got in construct

    private $outletId = '3e48a4bb-15c3-4704-b1ec-904e71f70700'; // Trade point
    private $fnFactoryNumber = '9287440300556461'; // ID cash machine

    public function __construct()
    {
        parent::__construct();

        $this->headers = array(
            'Content-Type: application/json;  charset=utf-8',
            "Integrator-ID: $this->token"
        );
        $this->host = "https://$this->host/API/v2/";

        $this->authorization();
        $this->headers[] = "Session-Token: $this->sessionToken";
    }

    public function authorization()
    {
        $res = $this->makeRequest(
            'POST',
            "Login",
            array(
                'login' => $this->login,
                'password' => $this->pass
            )
        );
        $this->sessionToken = $res->sessionToken??false;

        return $this->sessionToken;
    }


    public function getOrdersList($date_from)
    {
        $documentsList = [];
        $shiftList = $this->shiftList($date_from);

        foreach($shiftList as $key => $shift){
            print_r($key);
            if($shift->receiptCount > 0){
                $newDocumentsList = $this->documentList($shift->shiftNumber);
                $documentsList = array_merge($documentsList, $newDocumentsList);
            };
        };

        $orders = [];
        foreach($documentsList as $document){
            $orders[] = $this->documentInfo($document->fdNumber);
        }

        return $orders;
    }



    public function shiftList($date_from){
        $res = $this->makeRequest(
            'GET',
            "ShiftList",
            array(
                'fn' => $this->fnFactoryNumber,
                'begin' => $date_from,
                'end' => Carbon::now()->format('Y-m-d H:i:s'),
            )
        );
        return $res->records??[];
    }

    public function documentList($shiftNumber){
        $res = $this->makeRequest(
            'GET',
            "DocumentList",
            array(
                'fn' => $this->fnFactoryNumber,
                'shift' => $shiftNumber,
                'type' => '3'
            )
        );
        return $res->records??[];
    }

    public function documentInfo($fdNumber){
        $res = $this->makeRequest(
            'GET',
            "DocumentInfo",
            array(
                'fn' => $this->fnFactoryNumber,
                'fd' => $fdNumber
            )
        );
        return $res->document;
    }

    // Not need now
    public function outletList(){
        $res = $this->makeRequest(
            'GET',
            "OutletList"
        );
        print_r($res);
        //0bd9d6dd-5c1b-493c-a71d-f04a8c52ac51
        //3e48a4bb-15c3-4704-b1ec-904e71f70700 !!
    }

    public function kKTList()
    {
        $res = $this->makeRequest(
            'GET',
            "KKTList",
            array('id' => $this->outletId)
        );
        print_r($res);
        //[id] => 3e48a4bb-15c3-4704-b1ec-904e71f70700
        //[kktRegNumber] => 0003896923025561
        //[fnFactoryNumber] => 9287440300556461
    }
}
