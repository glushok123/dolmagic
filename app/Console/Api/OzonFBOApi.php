<?php

namespace App\Console\Api;

use App\Eloquent\Other\Ozon\OzonFboReturn;
use App\Eloquent\Sales\Sale;
use App\Models\Orders;
use App\Models\Shops\Shops;
use App\Models\Users\Notifications;
use Carbon\Carbon;

class OzonFBOApi extends OzonApi
{
    public $shopId = 74; // OzonSTV (FBO)

    public function  __construct($alias)
    {
        parent::__construct($alias);
    }

    public function getOrdersList($date_from)
    {
        return $this->getOrdersStack($date_from);
    }

    public function getOrdersStack($date_from, $orders = array(), $offset = 0, $cycle = 0)
    {
        $limit = 50;
        $req = [
            'dir' => 'asc',
            'filter' => [
                'since' =>  Carbon::parse($date_from, 'Europe/Moscow')->format('Y-m-d\TH:i:s.000\Z'),
                'to' =>     Carbon::now()->setTimezone('Europe/Moscow')->format('Y-m-d\TH:i:s.000\Z')
            ],
            'limit' => $limit,
            'offset' => $offset,
            'with' => ['analytics_data' => true],
        ];
        $res = $this->makeRequest(
            'POST',
            '/v2/posting/fbo/list',
            $req
        );

        if(isset($res->error)){
            $this->log('error', 'getOrdersStack', 'POST /v2/posting/fbo/list code 1', $req, $res);
            die('exit code getOrdersStack 1');
        }else{
            $newOrders = $res->result;
            $orders = array_merge($orders, $newOrders);
        };

        if(count($newOrders) == $limit){
            print_r('Цикл: '.$cycle. PHP_EOL);
            if($cycle > 50){
                $this->log('error', 'getOrdersStack code 2', 'POST /v2/posting/fbo/list code 2', $req, $res);
                die('exit code getOrdersStack 2');
            };
            return $this->getOrdersStack($date_from, $orders, ($offset+$limit), $cycle+1);
        }else{
            return $orders;
        }
    }

    public function getOrder($orderId, $withAnalyticsData = true, $withFinancialData = false)
    {

        $req = [
            'posting_number' => $orderId,
            'with' => [
                'analytics_data' => $withAnalyticsData,
                'financial_data' => $withFinancialData,
            ],
        ];
        $res = $this->makeRequest(
            'POST',
            '/v2/posting/fbo/get',
            $req
        );

        if(!isset($res->result) or isset($res->error))
        {
            $this->log('error', 'getOrder', 'POST /v2/posting/fbo/get code 1', $req, $res);
            return false;
        }else{
            return $res->result;
        }
    }

    public function getReturns($offset = 0)
    {
        $returns = [];
        $limit = 1000;
        $res = $this->makeRequest(
            'POST',
            '/v2/returns/company/fbo',
            [
                'limit' => $limit,
                'offset' => $offset,
            ]
        );

        if(isset($res->returns) and $res->returns)
        {
            $returns = $res->returns;
            if(count($returns) === $limit)
            {
                $returns = array_merge($returns, $this->getReturns($offset + $limit));
            }
        }

        return $returns;
    }

    public function saveReturns()
    {
        $returns = $this->getReturns();

        foreach($returns as $Return)
        {
            $exists = true;
            if(!$OzonFboReturn = OzonFboReturn::where('ozon_return_id', $Return->id)->first())
            {
                $OzonFboReturn = new OzonFboReturn;
                $exists = false;
            }
            $OzonFboReturn->shop_id = $this->shopId;
            $OzonFboReturn->ozon_return_id = $Return->id;
            $OzonFboReturn->accepted_from_customer_moment = $Return->accepted_from_customer_moment;
            $OzonFboReturn->company_id = $Return->company_id;
            $OzonFboReturn->current_place_name = $Return->current_place_name;
            $OzonFboReturn->dst_place_name = $Return->dst_place_name;
            $OzonFboReturn->is_opened = $Return->is_opened;
            $OzonFboReturn->posting_number = $Return->posting_number;
            $OzonFboReturn->return_reason_name = $Return->return_reason_name;
            $OzonFboReturn->returned_to_ozon_moment = $Return->returned_to_ozon_moment;
            $OzonFboReturn->sku = $Return->sku;
            $OzonFboReturn->status_name = $Return->status_name;
            if($OzonFboReturn->save())
            {
                $saleHref = $OzonFboReturn->posting_number;
                if($Order = Orders::getOrder($OzonFboReturn->posting_number, $this->systemId))
                {
                    if($Sale = $Order->sale??false)
                    {
                        $OzonFboReturn->sale_id = $Sale->id;
                        $OzonFboReturn->save();
                        $saleHref = "<a target = '_blank' href = '$Sale->EditReturnsPath'>$Sale->OrderNumber</a>";
                    }
                }
                if(!$exists)
                {
                    Notifications::new(
                        'Новый Ozon FBO возврат',
                        "По заказу $saleHref получен новый возврат. Требуется вернуть остатки.",
                        [1, 3]
                    );
                }
            }
        }
    }
}
