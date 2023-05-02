<?php

namespace App\Console\Api;

use App\Eloquent\System\SystemCron;
use App\Eloquent\System\System;
use App\Models\Orders;
use App\Models\Sales;
use App\Models\WarehouseMovements;
use App\Eloquent\Other\ApiLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class Api
{
    //dd(memory_get_peak_usage(true)/1048576 . ' MB usage');

    public $systemId;
    public $shopId;
    public $headers;
    public $host;
    public $CronOptions;
    public $System;
    public $showLog;

    public $Warehouse = false;

    public function __construct($showLog = false)
    {
        $this->showLog = $showLog;
        $this->System = $this->getSystem();
        $this->CronOptions = $this->getCronOptions();

        if($this->CronOptions)
            if(!$this->Warehouse){
                $this->Warehouse = ['id' => $this->CronOptions->warehouse_id];
            }
    }

    public function log($type, $fname, $comment, $request = false, $response = false)
    {
        //dump($type, $fname, $comment);
        /*
        $ApiLog = new ApiLog;
        $ApiLog->system_id = $this->systemId;
        if(isset($this->shopId)) $ApiLog->shop_id = $this->shopId;

        $userId = Auth::user()->id??0;
        $ApiLog->user_id = $userId;

        $ApiLog->type = $type;
        $ApiLog->fname = $fname;

        if($comment) $ApiLog->comment = is_string($comment)?$comment:json_encode($comment);
        if($request) $ApiLog->request = is_string($request)?$request:json_encode($request);
        if($response) $ApiLog->response = is_string($response)?$response:json_encode($response);

        $ApiLog->memory_peak_usage_mb = memory_get_peak_usage()/1024/1024;

        $ApiLog->save();

        if($this->showLog){
            print_r($ApiLog);
        };
        */
    }

    public function getSystem(){
        return System::where([
            ['id', '=', $this->systemId]
        ])->limit(1)->first();
    }

    public function getCronOptions($type = 1)
    {
        $query = [];
        //$query[] =  ['system_id', '=', $this->systemId];
        $query[] =  ['type_shop_id', '=', $this->shopId];
        $query[] =  ['cron_type', '=', $type];
        /*
        if($this->Warehouse){
            $query[] =  ['warehouse_id', '=', $this->Warehouse['id']];
        };
        */
        return SystemCron::where($query)->first();
    }

    public function updateCronTimestamp($cronTimestamp, $type = 1)
    {
        $SystemsCron = $this->getCronOptions($type);
        if(Carbon::parse($SystemsCron->cron_timestamp) < Carbon::parse($cronTimestamp))
        {
            $SystemsCron->cron_timestamp = $cronTimestamp;
            $SystemsCron->save();
        }
    }

    public $countWait = 0;
    public function waitRequest($method, $event, $body, $retryAfter = 2)
    {
        while($retryAfter > 0)
        {
            //echo "$method $event: CURL USAGE - Waiting for $retryAfter sec \r";
            Sleep(1);
            $retryAfter--;
        };
        $this->countWait++;
        if($this->countWait === 15)
        {
            dd('error', 'API waitRequest', '15 sec and 15 tries wait total');
            die();
        }
        return $this->makeRequest($method, $event, $body);
    }

    public function makeRequest($method, $event, $body = false, $showResponse = false, $returnJson = true, $showHeader = false)
    {
        $this->countWait = 0;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        if(strtoupper($method) == 'POST'){
            curl_setopt($ch, CURLOPT_POST, true);
        };

        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // from 2022-09-13

        switch(strtoupper($method)){
            case 'GET':
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                if(!empty($body)){
                    $event .= '?'.http_build_query($body);
                };
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);
                if(!empty($body)){
                    $jsonDataEncoded = json_encode($body, JSON_UNESCAPED_UNICODE);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
                };
            break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if(!empty($body)){
                    $jsonDataEncoded = json_encode($body, JSON_UNESCAPED_UNICODE);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
                };
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                if(!empty($body)){
                    $jsonDataEncoded = json_encode($body, JSON_UNESCAPED_UNICODE);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
                };
                break;
        };


        curl_setopt($ch, CURLOPT_URL, $this->host . $event);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //test anti empty resp
        curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17');
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);


        curl_setopt($ch, CURLOPT_HEADER, 1);
        $response  = curl_exec($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if(in_array($http_code, [0, 504, 429]))
        {
            dump( curl_error($ch));
            return $this->waitRequest($method, $event, $body);
        }

        $resHeader = substr($response, 0, $header_size);
        $resBody = substr($response, $header_size);

        if($showResponse) print_r($response);
        if($showHeader)
        {
            dump('http_code '.$http_code);
            if($http_code === 0) dump( curl_error($ch));
            dump($resHeader);
        }

        curl_close($ch);

        if(($this->systemId === 1) and empty($resBody)) // errors from Insales
        {
            /*
            API-Usage-Limit: 501/500
            Retry-After: 164
            */
            $param = 'Retry-After: ';
            $pos = mb_stripos($resHeader, $param)+mb_strlen($param);
            if($pos === false) return false;
            $pos2 = mb_stripos($resHeader, PHP_EOL, $pos);
            if($pos2 === false) return false;
            $retryAfter = (int) mb_substr($resHeader, $pos, $pos2-$pos);

            $this->log('warning', 'makeRequest 1', 'w8 insales to send'.$event, $body, $resBody);
            return $this->waitRequest($method, $event, $body, $retryAfter);
        }else{
            if($returnJson)
            {
                $resObj = json_decode($resBody);
                if(
                    (
                        isset($resObj->error->code)
                            and
                        in_array($resObj->error->code, array('TOO_MANY_REQUESTS', 'REQUEST_TIMEOUT'))
                    )
                    or
                    (
                        isset($resObj->message)
                            and
                        ($resObj->message === 'You have reached request rate limit per second')
                    )
                ){ // error from ozon
                    $this->log('warning', 'makeRequest 2', 'w8 ozon to send '.$event, $body, $resBody);
                    return $this->waitRequest($method, $event, $body);
                }else{
                    return $resObj;
                }
            }else{
                return $resBody;
            }
        }
    }

    public function updateUncompletedOrders($uncompletedOrders = false, $update = false)
    {
        // Monitoring and updating uncompleted orders.
        // default value. If changed, old orders cannot be updated.
        $updateSince = Carbon::now()->subMonths(3)->startOfDay()->setTimezone('UTC')->toDateTimeString();

        if(!$uncompletedOrders) $uncompletedOrders = Orders::getUncompleted($this->shopId, $updateSince);

        $countUncompletedOrders = count($uncompletedOrders);
        if($countUncompletedOrders === 0) return; // exit if no one order

        //dd($uncompletedOrders->pluck('system_order_id')->toArray());

        $this->log('info', 'updateUncompletedOrders', "К обновлению заказов: $countUncompletedOrders");
        dump("К обновлению заказов: $countUncompletedOrders");

        foreach($uncompletedOrders as $UOrder)
        {
            $SystemOrder = $this->getOrder($UOrder->system_order_id);

            if(!$SystemOrder)
            {
                $this->log(
                    'error',
                    'updateUncompletedOrders',
                    "Заказ не найден:
                        id $UOrder->id,
                        system_order_id $UOrder->system_order_id,
                        order_system_number $UOrder->order_system_number"
                );
                dump('Заказ не найден'. ' ' . $UOrder->system_order_id);
                continue; // check next orders
            }

            // get system status order
            $SystemOrderStatusId = Orders::getSystemOrderField($SystemOrder, $this->systemId, 'systemsOrdersStatusId');
            $statusId = Orders::getSystemOrderField($SystemOrder, $this->systemId, 'statusId');

            // check status to update
            if(
                ($this->shopId === 3)
                or in_array($this->systemId, [74, 174])
                or ($UOrder->info->systems_orders_status_id !== $SystemOrderStatusId)
                or ($this->systemId === 68)
                or ($this->systemId === 177)
                or ($this->systemId === 179)
                or $update
            )
            {
                // There is updating order info
                Orders::updateOrderInfo($this->systemId, $UOrder->id, $SystemOrder);

                // There is updating order packs
                Orders::updateOrdersProductsPack($this->systemId, $UOrder->id, $SystemOrder);

                // There is updating order packs
                Orders::updateOrderPacksProducts($this->systemId, $UOrder, $SystemOrder, ($this->Warehouse['id']??false));
            };
        }
    }

    // Here is getting new orders
    public function getNewOrders($dateFrom = false, $subDays = 1)
    {
        $dateFrom = $dateFrom?:(Carbon::parse($this->CronOptions->cron_timestamp)->subDays($subDays)->toDateTimeString());
        $ordersList = $this->getOrdersList($dateFrom);

        if($ordersList)
        {
            $this->log('info', 'getNewOrders', 'To create '.count($ordersList));

            $dateCreated = false;
            foreach($ordersList as $SystemOrder)
            {
                $shopId = Orders::getOrderShopId($SystemOrder, $this->CronOptions->type_shop_id);
                $SystemOrderId = Orders::getSystemOrderField($SystemOrder, $this->systemId, 'id');

                $Order = Orders::getOrder($SystemOrderId, NULL, $shopId); // Find order

                if($Order){ // Order exist in DB
                    //Nothing to do. But it's a problem?
                    //$this->log('warning', 'getNewOrders', "Order already exist in DB: $SystemOrderId");
                }else{ // Create all order
                    $this->log('info', 'getNewOrders', "To create $SystemOrderId");

                    $fake = Orders::getSystemOrderField($SystemOrder, $this->systemId, 'fake');
                    $crmUse = Orders::getSystemOrderField($SystemOrder, $this->systemId, 'crm_use');

                    $Order = Orders::createOrder(
                        $this->systemId,
                        $shopId,
                        $SystemOrderId,
                        Orders::getSystemOrderField($SystemOrder, $this->systemId, 'number'),
                        $fake,
                        $crmUse
                    );

                    if(!$Order)
                    {
                        $this->log('error', 'getNewOrders', 'Can\'t create order - id '.$SystemOrderId);
                        continue;
                    };

                    //Create order info
                    $OrdersInfo = Orders::createOrderInfo($this->systemId, $Order->id, $SystemOrder);

                    if(empty($OrdersInfo))
                    {
                        $Order->delete();
                        $this->log('error', 'getNewOrders', 'Can\'t create order info - id '.$SystemOrderId);
                    }else{
                        $dateCreated = Orders::getSystemOrderField($SystemOrder, $this->systemId, 'dateCreated2');
                        //$products = Orders::getSystemOrderField($SystemOrder, $this->systemId, 'products', $this->Warehouse['id']);
                        $products = Orders::getSystemOrderField($SystemOrder, $this->systemId, 'products');

                        $deliveryPrice = Orders::getSystemOrderField($SystemOrder, $this->systemId, 'priceDelivery');
                        $deliveryTypeId = Orders::getSystemOrderField($SystemOrder, $this->systemId, 'deliveryTypeId');

                        if(!Orders::createOrderProducts($SystemOrder, $Order, $products, $deliveryPrice, $deliveryTypeId))
                        {
                            $Order->delete();
                            if($OrdersInfo) $OrdersInfo->delete();
                            $this->log('error', 'getNewOrders', 'Can\'t create order products - id '.$SystemOrderId);
                        }else{
                            // there is order is created
                            Orders::NewOrderNotify($Order);

                            Orders::checkSystemOrdersStatuses($Order);

                            // There is updating order commission (or create)
                            //Sales::updateSaleCommission($Order, $SystemOrder);
                        };
                    };
                };
            };

            // Обновляем сразу дату последнего сбора на дату последнего заказа
            if($dateCreated) $this->updateCronTimestamp($dateCreated);
        }
    }
}
