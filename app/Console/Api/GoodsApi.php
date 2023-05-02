<?php

namespace App\Console\Api;

class GoodsApi extends Api
{
    public $systemId = 4; // Goods = 4
    public $shopId = 5;

    public $token = '9670A80B-1F8E-4857-8589-D355E0A98FF7'; // Your authorization token
    //public $host = 'https://partner.goods.ru/api/market/v1'; // Old host
    public $host = 'https://partner.sbermegamarket.ru/api/market/v1'; // Host

    public $vat = '0'; // НДС, возможные значения: 0, 0.1, 0.2
    public $deliverySchema = 'fbs';

    public function __construct()
    {
        parent::__construct();

        $this->СronOptions = $this->getCronOptions();
        $this->headers = array(
            'Content-Type: application/json'
        );
    }

    /* FUNCTIONS */

    public function getOrdersStack($date_from, $orders = array(), $page = 1, $cycle = 0){
        $newOrders = $this->makeRequest(
            'POST',
            '/orderService/order/search',
            array(
                'data' => [
                    'token' => $this->token,
                    'dateFrom' => date('Y-m-d H:i:s', strtotime($date_from)),
                    'dateTo' => date('Y-m-d H:i:s'),
                    'statuses' => [
                        'NEW',
                        'CONFIRMED',
                        'PACKED',
                        'PACKING_EXPIRED',
                        'SHIPPED',
                        'DELIVERED',
                        'MERCHANT_CANCELED',
                        'CUSTOMER_CANCELED'
                    ]
                ],
                'meta' => []
            )
        );

        if(isset($newOrders->data->shipments)){
            $orders = array_merge($orders, $newOrders->data->shipments);
        }else{
            print_r($newOrders);
            die('exit code getOrdersStack 1');
        };

        if(count($newOrders->data->shipments) == 100){
            die('exit code getOrdersStack 2'); // Too many orders
        }else{
            return $orders;
        };
    }

    private function getOrdersInfo($ordersId){
        $newOrders = array();
        $orderInfo = $this->makeRequest( // limit 5 per second
            'POST',
            '/orderService/order/get',
            array(
                'data' => [
                    'token' => $this->token,
                    'shipments' => $ordersId
                ],
                'meta' => []
            )
        );

        if(isset($orderInfo->data->shipments)){
            foreach($orderInfo->data->shipments as $shipment){
                $newOrder = $shipment;
                $orderSum = 0;
                foreach($newOrder->items as $product){
                    $orderSum += $product->price;
                };
                $newOrder->price = $orderSum;
                $newOrders[] = $newOrder;
            };
        }else{
            //print_r($orderInfo);
            //die('exit code getOrdersInfo 1');
        };

        return $newOrders;
    }

    public function getOrdersList($date_from = false)
    {
        $ordersId = $this->getOrdersStack($date_from);
        $orders = $this->getOrdersInfo($ordersId);
        return $orders;
    }


    // Only getting clear order
    public function getOrder($systemOrderId)
    {
        $order = $this->makeRequest( // limit 5 per second
            'POST',
            '/orderService/order/get',
            array(
                'data' => [
                    'token' => $this->token,
                    'shipments' => [$systemOrderId]
                ],
                'meta' => []
            )
        );
        return $order->data->shipments[0]??false;
    }
}
