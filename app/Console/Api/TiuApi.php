<?php

namespace App\Console\Api;

use App\Models\Products;
use Carbon\Carbon;

class TiuApi extends Api
{
    public $systemId = 2; // TIU = 2
    public $shopId = 4; // TIU
    public $token = 'e96994d5f4f3bd5df7bb4a1290fc9614ff0417fd'; // Your authorization token
    public $host = 'https://my.tiu.ru'; // e.g.: my.prom.ua, my.tiu.ru, my.satu.kz, my.deal.by, my.prom.md
    public $timezone = 'Europe/Moscow';

    public function __construct()
    {
        parent::__construct();

        $this->headers = array(
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        );
    }

    public function getOrdersStack($date_from, $orders = array(), $lastId = false, $cycle = 0){
        $event = '/api/v1/orders/list';

        $req = array();
        $req['date_from'] = $date_from; //2015-04-28T12:50:34
        $req['limit'] = 100;
        if($lastId){
            $req['last_id'] = $lastId;
        };

        $newOrders = $this->makeRequest('GET', '/api/v1/orders/list', $req);

        if(!empty($newOrders->orders)){
            $newOrders = $newOrders->orders;
            $newLastId = end($newOrders)->id;
            $orders = array_merge($orders, $newOrders);

            if(count($newOrders) == 100){
                print_r('Цикл: '.$cycle. PHP_EOL);
                if($cycle > 10){
                    print_r($newOrders);
                    die();
                }
                return $this->getOrdersStack($date_from, $orders, $newLastId, $cycle+1);
            };
        };
        return $orders;
    }

    public function getOrdersList($dateFrom = false)
    {
        $orderListTIU = $this->getOrdersStack($dateFrom);
        if($orderListTIU){
            $ordersList = array_reverse($orderListTIU);
            unset($orderListTIU);
            return $ordersList;
        };
        return false;
    }

    public function getOrder($orderId){
        $order = $this->makeRequest('GET', "/api/v1/orders/$orderId")->order??false;
        return $order;
    }

    public function getGroups($perPage = 100)
    {
        $tiuGroups = array();

        $stop = false;
        $stopper = 0;
        $lastId = false;

        while(!$stop)
        {
            $req = array();
            $req['limit'] = $perPage;
            if($lastId) $req['last_id'] = $lastId;

            $res = $this->makeRequest('GET', '/api/v1/groups/list', $req);
            $groups = $res->groups??false;

            if($groups){
                $lastId = end($groups)->id;
                if(count($groups) > 0) $tiuGroups = array_merge($tiuGroups, $groups);
                if(count($groups) < $perPage) $stop = true;
            }else{
                $stop = true;
            };

            $stopper++;
            if($stopper == 50) $stop = true;
        };

        return $tiuGroups;
    }

    public function getProducts($perPage = 100)
    {
        $tiuGroups = $this->getGroups();
        $tiuProducts = array();

        foreach($tiuGroups as $TiuGroup)
        {
            $stop = false;
            $stopper = 0;
            $lastId = false;

            while(!$stop)
            {
                $req = array();
                $req['limit'] = $perPage;
                $req['group_id'] = $TiuGroup->id;
                if($lastId) $req['last_id'] = $lastId;

                $res = $this->makeRequest('GET', '/api/v1/products/list', $req);
                $products = $res->products??false;

                if($products){
                    $lastId = end($products)->id;
                    if(count($products) > 0) $tiuProducts = array_merge($tiuProducts, $products);
                    if(count($products) < $perPage) $stop = true;
                }else{
                    $stop = true;
                };

                $stopper++;
                if($stopper == 50) $stop = true;
            };
        }

        return $tiuProducts;
    }


    public function sendPriceAndQuantity($updates)
    {
        if($updates)
        {
            $res = $this->makeRequest('POST', '/api/v1/products/edit', $updates);

            if(isset($res->processed_ids))
            {
                $successCount = count($res->processed_ids);
                if($successCount === count($updates))
                {
                    return $successCount;
                }else{
                    $this->log('error', 'sendPriceAndQuantity', '2', $updates, $res);
                    return $successCount;
                }
            }else{
                $this->log('error', 'sendPriceAndQuantity', '1', $updates, $res);
                return 0;
            }
        }

        return false;
    }

    public function updatePriceAndQuantity()
    {
        $start = microtime(true);
        $tiuProducts = $this->getProducts();
        $productsCount = count($tiuProducts);
        $productsError = 0;
        $productsSuccess = 0;

        $updates = [];
        $i = 0;
        $max = 100;
        foreach($tiuProducts as $key => $TiuProduct)
        {
            $Update = new \stdClass();
            $Update->id = $TiuProduct->id;

            $quantity = 0;
            if($Product = Products::getProductBy('sku', $TiuProduct->sku))
            {
                if($Product->temp_price > 0)
                {
                    $quantity = $Product->shopAmounts($this->shopId)->amounts->balance??0;
                    if($Product->temp_old_price > 0)
                    {
                        $Update->price = $Product->temp_old_price;

                        $discountValue = $Product->temp_old_price - $Product->temp_price;
                        if($discountValue > 0)
                        {
                            $Discount = new \stdClass();
                            $Discount->value = $discountValue;
                            $Discount->type = 'amount';
                            $Discount->date_start = Carbon::now()->setTimezone('Europe/Moscow')->subDays(8)->format('d.m.Y');  //'03.10.2020'
                            $Discount->date_end = Carbon::now()->setTimezone('Europe/Moscow')->addDays(1)->format('d.m.Y');
                            $Update->discount = $Discount;
                        }else{
                            $Update->price = $Product->temp_price;

                            $Discount = new \stdClass();
                            $Discount->value = 1;
                            $Discount->type = 'amount';
                            $Discount->date_start = Carbon::now()->setTimezone('Europe/Moscow')->subDays(8)->format('d.m.Y');
                            $Discount->date_end = Carbon::now()->setTimezone('Europe/Moscow')->subDays(1)->format('d.m.Y');
                            $Update->discount = $Discount;
                        }
                    }else{
                        $Update->price = $Product->temp_price;

                        $Discount = new \stdClass();
                        $Discount->value = 1;
                        $Discount->type = 'amount';
                        $Discount->date_start = Carbon::now()->setTimezone('Europe/Moscow')->subDays(8)->format('d.m.Y');
                        $Discount->date_end = Carbon::now()->setTimezone('Europe/Moscow')->subDays(1)->format('d.m.Y');
                        $Update->discount = $Discount;
                    }
                }
            }

            $Update->presence = ($quantity > 0)?'available':'not_available';

            $updates[] = $Update;

            $i++;
            if(($i === $max) or (($key+1) === count($tiuProducts)))
            {
                $i = 0;
                $successCount = $this->sendPriceAndQuantity($updates);
                $productsSuccess = $productsSuccess + $successCount;
                $productsError = $productsError + (count($updates) - $successCount);
                $updates = [];

            }
        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        $this->log('info', 'updatePriceAndQuantity', "Total: $productsCount, error: $productsError, success: $productsSuccess. $ExecutionTime");
    }
}
