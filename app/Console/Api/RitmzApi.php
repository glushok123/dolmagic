<?php

namespace App\Console\Api;

use App\Eloquent\Order\Order;
use App\Eloquent\Sales\Sale;
use Carbon\Carbon;
use SoapClient;
use SoapFault;

class RitmzApi extends Api
{
    public $systemId = 66; // Ritmz = 66
    public $shopId = 66;

    const url = 'http://cc.Ritm-Z.com:8008/RitmZ_GM82/ws/GetRemains.1cws?wsdl';
    const login = 'w238';
    const password = 'KhNCh9k7';
    const encoding = 'utf-8';

    public function __construct($showLog = false)
    {
        parent::__construct($showLog);
    }

    private static function get1CData($function, $parameters, $url = self::url)
    {
        $new_default_socket_timeout = 30;									// Для сокращения времени ожидания ответа от "мёртвого" сервера, при необходимости удалить

        $result = '';
        $tmp_default_socket_timeout = ini_get( "default_socket_timeout" );	// Для сокращения времени ожидания ответа от "мёртвого" сервера, при необходимости удалить
        ini_set( "default_socket_timeout", $new_default_socket_timeout );	// Для сокращения времени ожидания ответа от "мёртвого" сервера, при необходимости удалить
        try {
            $client = new SoapClient(
                $url,
                array( "login" => self::login, "password" => self::password, "encoding" => self::encoding ) );
            $result = $client->__call( $function, array( 'parameters' => $parameters ) );
            $result = $result->return;
        } catch ( SoapFault $e ) {
            $result = 'Ошибка: ' . $e->faultcode . ': ' . $e->faultstring;
        }
        ini_set( "default_socket_timeout", $tmp_default_socket_timeout );	// Для сокращения времени ожидания ответа от "мёртвого" сервера, при необходимости удалить
        return $result;
    }

    public static function getRemains($id = false){
        $FUNC = "ПолучитьОстатки";
        $FUNC_PARAM  = array(
            "Склад" => "Основной склад W238" // нужно передавать пустое значение или чтото
        );
        if($id){
            $FUNC_PARAM['Номенклатура'] = $id;
        };
        $remains = self::get1CData($FUNC, $FUNC_PARAM);
        return $remains->remains;
    }

    /*
    public static function getOrders($id = false)
    {
        $FUNC = "ПолучитьЗаказы";
        $FUNC_PARAM  = array(
            "НачалоПериода" => "2022-05-24",
            "ОкончаниеПериода" => "2022-05-25"
        );
        $url = 'http://cc.Ritm-Z.com:8008/RitmZ_GM82/ws/GetOrders.1cws?wsdl';

        $remains = self::get1CData($FUNC, $FUNC_PARAM, $url);
        return $remains;
    }
    */

    public function getOrder(Order $Order)
    {
        $FromDate = Carbon::createFromDate($Order->info->order_date_create)->subDays(1)->toDateString();
        $ToDate = Carbon::createFromDate($Order->info->order_date_create)->addDays(1)->toDateString();

        $FUNC = "ПолучитьЗаказы";
        $FUNC_PARAM  = array(
            "НачалоПериода" => $FromDate,
            "ОкончаниеПериода" => $ToDate
        );
        $url = 'http://cc.Ritm-Z.com:8008/RitmZ_GM82/ws/GetOrders.1cws?wsdl';

        $res = self::get1CData($FUNC, $FUNC_PARAM, $url);

        if(isset($res->orders))
        {
            foreach($res->orders as $RitmzOrder)
            {
                if($RitmzOrder->id === $Order->system_order_id)
                {
                    return $RitmzOrder;
                }
            }
        }else{
            $this->log('error', 'getOrderStatus', "Order id $Order->id", $FUNC_PARAM, $res);

        }

        return false;
    }

    public function getOrders($fromDate = false, $toDate = false)
    {
        $FromDate = Carbon::parse($fromDate)->toDateString();
        if(!$toDate) $toDate = Carbon::now()->toDateString();
        $ToDate = Carbon::parse($toDate)->toDateString();

        $FUNC = "ПолучитьЗаказы";
        $FUNC_PARAM  = array(
            "НачалоПериода" => $FromDate,
            "ОкончаниеПериода" => $ToDate
        );
        $url = 'http://cc.Ritm-Z.com:8008/RitmZ_GM82/ws/GetOrders.1cws?wsdl';

        $res = self::get1CData($FUNC, $FUNC_PARAM, $url);

        if(isset($res->orders))
        {
            return $res->orders;
        }else{
            var_dump($res);
        }

        return false;
    }



}
