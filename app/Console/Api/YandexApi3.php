<?php

namespace App\Console\Api;

use App\Eloquent\Deliveries\DeliveryPickup;
use App\Eloquent\Products\Product;
use App\Eloquent\Products\TypeShopProduct;
use App\Eloquent\Products\TypeShopProductsOption;
use App\Eloquent\Shops\ShopDeliveryService;
use App\Eloquent\System\SystemsOrdersStatus;
use App\Eloquent\Warehouse\TypeShopWarehouse;
use App\Models\API\Yandex;
use App\Models\Operations\Operations;
use App\Models\Products;
use Carbon\Carbon;

class YandexApi3 extends Api
{
    // DBS https://yandex.ru/dev/market/partner-dsbs/doc/dg/concepts/about.html
    public $systemId = 74;
    public $shopId = 71;
    public $appSecret = 'ed88b6a3b9c41537e27a38612b55bfd7';
    public $campaignId = '21973279';
    public $host = "https://api.partner.market.yandex.ru/v2";

    //https://oauth.yandex.ru/authorize?response_type=token&client_id=f7594d2d0c6c43818c4eee7fb1932865

    public function __construct()
    {
        parent::__construct();

        $this->headers = array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: OAuth oauth_token="AQAAAAAcHKaTAAaoXO23XJD8r0potCCjIVnogMM", oauth_client_id="f7594d2d0c6c43818c4eee7fb1932865"'
        );
    }

    public function changeOrderStatus($Order, $systems_orders_status_id, $deliveredDate = false)
    {
        $body = new \stdClass();
        $body->order = new \stdClass();
        $body->order->status = SystemsOrdersStatus::where('id', $systems_orders_status_id)->firstOrFail()->alias;

        if($deliveredDate and in_array($body->order->status, ['DELIVERED', 'PICKUP']))
        {
            $body->order->delivery = new \stdClass();
            $body->order->delivery->dates = new \stdClass();
            $body->order->delivery->dates->realDeliveryDate = Carbon::parse($deliveredDate)->format('d-m-Y');
        }

        if(mb_stripos($body->order->status, 'CANCELLED-') !== false)
        {
            $arrStatus = explode('-', $body->order->status);
            $body->order->status = $arrStatus[0];
            $subStatus = $arrStatus[1];
            if($subStatus)
                $body->order->substatus = $subStatus;

            //$body->order->substatus = 'USER_CHANGED_MIND';
            //SHOP_FAILED — магазин не может выполнить заказ.
            //REPLACING_ORDER — покупатель решил изменить состав заказа.
            //USER_CHANGED_MIND — покупатель отменил заказ по собственным причинам.
        }

        $event = "/campaigns/$this->campaignId/orders/$Order->system_order_id/status.json";
        $res = $this->makeRequest(
            'PUT',
            $event,
            $body,
            true,
            true,
            true
        );

        $error = false;

        if(isset($res->status) and ($res->status === 'ERROR'))
        {
            $this->log('error', 'changeOrderStatus', 'Error when change status', $body, $res);
            $error = isset($res->errors[0]->message)?$res->errors[0]->message:'changeOrderStatus';
        }

        return $error;
    }

    public function cancellationAcceptSend($systemOrderId, $req)
    {
        $event = "/campaigns/$this->campaignId/orders/$systemOrderId/cancellation/accept.json";
        $res = $this->makeRequest(
            'PUT',
            $event,
            $req,
            NULL,
            false,
        );

        if($jsonRes = json_decode($res))
        {
            return $jsonRes;
        }
        return false;
    }

    public function changeDeliveryDate($systemOrderId, $dateTo, $reason)
    {
        $req = new \stdClass();
        $req->dates = new \stdClass();
        $req->dates->toDate = Carbon::parse($dateTo)->format('d-m-Y');
        $req->reason = $reason;

        $event = "/campaigns/$this->campaignId/orders/$systemOrderId/delivery/date.json";
        $res = $this->makeRequest(
            'PUT',
            $event,
            $req,
        );

        if(isset($res->status) and ($res->status === 'ERROR'))
        {
            return $res->errors[0]->message??'Ошибка YA3-109';
        }else
        {
            return false;
        }
    }

    public function getRegionId($regionName)
    {
        $event = "/regions.json";
        $res = $this->makeRequest(
            'GET',
            $event,
            ['name' => $regionName]
        );
       if(isset($res->regions) and (count($res->regions) > 0))
       {
           return $res->regions[0];
       }else{
           return false;
       }
    }

    public function getOutletTemplate($Outlet, $CDEKApi)
    {
        $outlet = new \stdClass();
        $outlet->name = $Outlet->name;
        $outlet->type = 'DEPOT';
        $outlet->storagePeriod = 7; // FOR CDEK
        $outlet->coords = $Outlet->location->longitude . ' ' . $Outlet->location->latitude;
        $outlet->isMain = false;
        $outlet->shopOutletCode = $Outlet->code;
        $outlet->visibility = 'VISIBLE';

        $outlet->address = new \stdClass();
        $Region = $this->getRegionId($Outlet->location->city);



        if(!$Region) return false;
        $outlet->address->regionId = $Region->id;

        $expLocationAddress = explode(',', $Outlet->location->address);
        $outlet->address->street = $expLocationAddress[0];
        $outlet->address->number = $expLocationAddress[1];
        if(isset($Outlet->address_comment) or isset($Outlet->note) or isset($Outlet->additional))
            $outlet->address->additional = ($Outlet->address_comment??$Outlet->note)??$Outlet->additional;

        $outlet->phones = [];
        foreach($Outlet->phones as $Phone)
        {
            $outlet->phones[] = Operations::phoneFormat($Phone->number);;
        }

        $outlet->workingSchedule = new \stdClass();
        $outlet->workingSchedule->scheduleItems = [];

        foreach($Outlet->work_time_list as $WorkTime)
        {
            $ScheduleItem = new \stdClass();
            $dayName = $this->getDayName($WorkTime->day);
            $ScheduleItem->startDay = $dayName;
            $ScheduleItem->endDay = $dayName;

            $time = explode('/', $WorkTime->time);
            $ScheduleItem->startTime = $time[0];
            $ScheduleItem->endTime = $time[1];
            $outlet->workingSchedule->scheduleItems[] = $ScheduleItem;
        }

        $outlet->deliveryRules = [];
        $deliveryRules = new \stdClass();
        //$DeliveryCost = Yandex::getDeliveryCost($Region, 0, 3, $this->shopId);
        //if(!$DeliveryCost or is_null($DeliveryCost->price)) return false;
        //$deliveryRules->cost = $DeliveryCost->price;

        $deliveryRules->deliveryServiceId = 51; // CDEK

        $OutletDeliveryInfo = $CDEKApi->getOutletDeliveryInfoV2($Outlet);
        if(!$OutletDeliveryInfo) return false;
        if(!isset($OutletDeliveryInfo->period_min)) return false;
        $deliveryRules->minDeliveryDays = $OutletDeliveryInfo->period_min;
        $deliveryRules->maxDeliveryDays = $OutletDeliveryInfo->period_max??$OutletDeliveryInfo->period_min;
        $deliveryRules->orderBefore = 24; // was 17


        $FreeDeliveryCost = Yandex::getFreeDeliveryCost($Region, 3);
        if($FreeDeliveryCost)
            $deliveryRules->priceFreePickup = $FreeDeliveryCost->price_from;

        $outlet->deliveryRules[] = $deliveryRules;

        return $outlet;
    }

    public function OzonRocketGetOutletTemplate($OzonOutlet, OzonRocketApi $OzonRocketApi)
    {

        $outlet = new \stdClass();
        $outlet->name = $OzonOutlet->name;
        $outlet->type = 'DEPOT';
        $outlet->coords = $OzonOutlet->long . ' ' . $OzonOutlet->lat;
        $outlet->isMain = false;
        $outlet->shopOutletCode = $OzonOutlet->code;
        $outlet->visibility = 'VISIBLE';

        $outlet->address = new \stdClass();
        $Region = $this->getRegionId('Краснодар');
        if(!$Region) return false;
        $outlet->address->regionId = $Region->id;
        $expLocationAddress = explode(',', $OzonOutlet->placement);
        $outlet->address->street = $expLocationAddress[0];
        $outlet->address->number = $expLocationAddress[1];
        if(isset($OzonOutlet->howToGet) and $OzonOutlet->howToGet)
            $outlet->address->additional = $OzonOutlet->howToGet;

        $outlet->phones = [];
        if(!isset($OzonOutlet->phone)) return false;
        $outlet->phones[] = Operations::phoneFormat($OzonOutlet->phone);

        $outlet->workingSchedule = new \stdClass();
        $outlet->workingSchedule->scheduleItems = [];

        if(!isset($OzonOutlet->workingHours)) return false;

        foreach($OzonOutlet->workingHours as $key => $WorkingHour)
        {
            if(($key+1) > 7) continue; // only 7 days
            $ScheduleItem = new \stdClass();

            $dayName = $this->getDayName(Carbon::parse($WorkingHour->date)->weekday()+1);
            $ScheduleItem->startDay = $dayName;
            $ScheduleItem->endDay = $dayName;


            $ScheduleItem->startTime =
                str_pad($WorkingHour->periods[0]->min->hours, 2, '0', STR_PAD_LEFT)
                .':'. str_pad($WorkingHour->periods[0]->min->minutes, 2, '0', STR_PAD_LEFT);

            $ScheduleItem->endTime =
                str_pad($WorkingHour->periods[0]->max->hours, 2, '0', STR_PAD_LEFT)
                .':'. str_pad($WorkingHour->periods[0]->max->minutes, 2, '0', STR_PAD_LEFT);

            $outlet->workingSchedule->scheduleItems[] = $ScheduleItem;
        }

        $outlet->deliveryRules = [];
        $deliveryRules = new \stdClass();
        $DeliveryCost = Yandex::getDeliveryCost($Region, 0, 6, $this->shopId); // 6 is for DBS2
        if(!$DeliveryCost or is_null($DeliveryCost->price)) return false;
        $deliveryRules->cost = $DeliveryCost->price;

        if(!$days = $OzonRocketApi->getDeliveryTime($OzonOutlet->id)) return false;

        $deliveryRules->minDeliveryDays = $days;
        $deliveryRules->maxDeliveryDays = $days;
        $deliveryRules->orderBefore = 24; // was 17


        /* is old rules
        $FreeDeliveryCost = Yandex::getFreeDeliveryCost($Region, 3);
        if($FreeDeliveryCost)
            $deliveryRules->priceFreePickup = $FreeDeliveryCost->price_from;
        */

        $outlet->deliveryRules[] = $deliveryRules;

        return $outlet;
    }

    public function getOutlets($filter = [], $outlets = [], $page = 1)
    {
        $filter['pageSize'] = 50;
        $filter['page'] = $page;

        $event = "/campaigns/$this->campaignId/outlets.json";
        $res = $this->makeRequest(
            'GET',
            $event,
            $filter
        );

        if(isset($res->outlets) and (count($res->outlets) > 0))
        {
            $outlets = array_merge($outlets, $res->outlets);
            return $this->getOutlets($filter, $outlets, ($page + 1));
        }

        return $outlets;
    }

    public function saveOutletsOptions($DeliveryPickup, $region)
    {
        switch($region->type)
        {
            case "CITY":
                $DeliveryPickup->city_id = $region->id;
                break;
            case "REPUBLIC_AREA":
                $DeliveryPickup->republic_area_id = $region->id;
                break;
            case "REPUBLIC":
                $DeliveryPickup->republic_id = $region->id;
                break;
            case "COUNTRY_DISTRICT":
                $DeliveryPickup->country_district_id = $region->id;
            break;
            case "COUNTRY":
                $DeliveryPickup->country_id = $region->id;
                break;
        }

        if(isset($region->parent)) $this->saveOutletsOptions($DeliveryPickup, $region->parent);
    }

    public function saveOutletsIds()
    {
        $OzonRocketApi = new OzonRocketApi();
        $deliveryPickups = DeliveryPickup::where('shop_id', $this->shopId)->get();
        $ozonOutlets = $OzonRocketApi->getDeliveryVariants();
        foreach($deliveryPickups as $DeliveryPickup)
        {
            $deliveryProviderId = Operations::getDeliveryProviderId($DeliveryPickup->code);

            if($deliveryProviderId === 2) // ozon rocket
            {
                foreach($ozonOutlets as $key => $OzonOutlet)
                {
                    if($DeliveryPickup->code == $OzonOutlet->code)
                    {
                        $DeliveryPickup->delivery_outlet_id = $OzonOutlet->id;
                        $DeliveryPickup->save();
                        unset($ozonOutlets[$key]);
                    }
                }
            }
        }
    }

    public function saveOutlets()
    {
        $keyCode = uniqid();
        $yandexOutlets = $this->getOutlets();

        $countYandexOutlets = count($yandexOutlets);
        $updated = 0;

        foreach($yandexOutlets as $key => $YandexOutlet)
        {
            $DeliveryPickup = DeliveryPickup::firstOrNew([
                'code' => $YandexOutlet->shopOutletCode,
                'shop_id' => $this->shopId,
            ]);

            $DeliveryPickup->key_code = $keyCode;

            $deliveryProviderId = Operations::getDeliveryProviderId($YandexOutlet->shopOutletCode);
            $DeliveryPickup->delivery_provider_id = $deliveryProviderId;

            if(isset($YandexOutlet->deliveryRules[0]->minDeliveryDays))
                $DeliveryPickup->days_min = $YandexOutlet->deliveryRules[0]->minDeliveryDays;

            if(isset($YandexOutlet->deliveryRules[0]->maxDeliveryDays))
                $DeliveryPickup->days_max = $YandexOutlet->deliveryRules[0]->maxDeliveryDays;

            $DeliveryPickup->region_id = $YandexOutlet->region->id;
            $this->saveOutletsOptions($DeliveryPickup, $YandexOutlet->region);
            if($DeliveryPickup->save()) $updated++;

            print_r($key.' of '.$countYandexOutlets."\r");
        }

        DeliveryPickup
            ::where('shop_id', $this->shopId)
            ->where('key_code', '!=', $keyCode)
            ->delete();


        dump("Saved/updated $updated. Total: $countYandexOutlets");
        $this->log('info', 'saveOutlets', "Saved/updated $updated. Total: $countYandexOutlets");
    }

    public function getOutletId($shopOutletCode)
    {
        $outlets = $this->getOutlets(['shop_outlet_code' => $shopOutletCode]);
        if($outlets)
        {
            return $outlets[0]->id;
        }else{
            return false;
        }
    }


    public function outletAdd($Outlet, $CDEKApi = false)
    {
        if(!$CDEKApi) $CDEKApi = new CDEKApi();

        $outlet = $this->getOutletTemplate($Outlet, $CDEKApi);

        if($outlet)
        {
            $event = "/campaigns/$this->campaignId/outlets.json";
            $res = $this->makeRequest(
                'POST',
                $event,
                $outlet
            );

            return $res;
        }

        return false;
    }

    public function outletUpdate($Outlet, $CDEKApi = false)
    {
        if(!$CDEKApi) $CDEKApi = new CDEKApi();

        $outletId = $this->getOutletId($Outlet->code);
        if(!$outletId) return false;
        $outlet = $this->getOutletTemplate($Outlet, $CDEKApi);

        if($outlet)
        {
            $event = "/campaigns/$this->campaignId/outlets/$outletId.json";
            $res = $this->makeRequest(
                'PUT',
                $event,
                $outlet
            );

            return $res;
        }else{
            return false;
        }
    }

    public function OzonRocketOutletUpdate($Outlet, $OzonRocketApi = false)
    {
        if(!$OzonRocketApi) $OzonRocketApi = new OzonRocketApi();

        $outletId = $this->getOutletId($Outlet->code);
        if(!$outletId) return false;
        $outlet = $this->OzonRocketGetOutletTemplate($Outlet, $OzonRocketApi);

        if($outlet)
        {
            $event = "/campaigns/$this->campaignId/outlets/$outletId.json";
            $res = $this->makeRequest(
                'PUT',
                $event,
                $outlet
            );

            return $res;
        }else{
            return false;
        }
    }

    public function OzonRocketOutletAdd($Outlet, $OzonRocketApi = false)
    {
        if(!$OzonRocketApi) $OzonRocketApi = new OzonRocketApi();

        $outlet = $this->OzonRocketGetOutletTemplate($Outlet, $OzonRocketApi);

        if($outlet)
        {
            $event = "/campaigns/$this->campaignId/outlets.json";
            return $this->makeRequest(
                'POST',
                $event,
                $outlet
            );
        }
    }

    public function OzonRocketOutletsUpdate()
    {
        $OzonRocketApi = new OzonRocketApi();
        $outlets = $OzonRocketApi->getDeliveryVariants();
        if(!$outlets) return false;
        $outletsCount = count($outlets);

        foreach($outlets as $key => $Outlet)
        {
            $res = $this->OzonRocketoutletAdd($Outlet, $OzonRocketApi);
            if(isset($res->errors) and ($res->errors[0]->code==='DUPLICATE_OUTLET_CODE'))
                $this->OzonRocketoutletUpdate($Outlet, $OzonRocketApi);

            print_r($key.' of '.$outletsCount."\r");
        }
    }

    public function CDEKOutletsUpdate()
    {
        // CDEK Outlets update
        $CDEKApi = new CDEKApi();
        $outlets = $CDEKApi->getDeliveryPoints();
        if(!$outlets) return false;
        $outletsCount = count($outlets);

        foreach($outlets as $key => $Outlet)
        {
            $res = $this->outletAdd($Outlet, $CDEKApi);
            if(
                $res
                and isset($res->errors)
                and ($res->errors[0]->code==='DUPLICATE_OUTLET_CODE'))
            {
                $this->outletUpdate($Outlet, $CDEKApi);
            }

            dump($key.' of '.$outletsCount);
        }
    }

    public function outletsUpdate()
    {
        $this->CDEKOutletsUpdate(); // CDEK

        print_r('Completed');
    }

    public function getDayName($numDay)
    {
        switch ($numDay)
        {
            case 1:
                return 'MONDAY';
                break;
            case 2:
                return 'TUESDAY';
                break;
            case 3:
                return 'WEDNESDAY';
                break;
            case 4:
                return 'THURSDAY';
                break;
            case 5:
                return 'FRIDAY';
                break;
            case 6:
                return 'SATURDAY';
                break;
            case 7:
                return 'SUNDAY';
                break;
        }
        return false;
    }

    public function geoSuggest($key)
    {
        $event = "/geo/suggest.json";
        $res = $this->makeRequest(
            'POST',
            $event,
            [
                'version' => 'v2',
                'name_part' => $key,
            ]
        );

        return $res;
    }

    public function getOrdersList($dateFrom, $nextPageToken = false, $stopper = 0, $dateTo = false)
    {

        $stopper++;
        if($stopper > 50)
        {
            $this->log('error', 'getOrdersList', 'Problem with pagination');
            die();
        }
        $req = [
            'updateFrom' => Carbon::parse($dateFrom)->format('Y-m-d')
        ];

        if($dateTo) $req['updateTo'] = Carbon::parse($dateTo)->format('Y-m-d');

        $event = "/campaigns/$this->campaignId/stats/orders.json?limit=200";
        if($nextPageToken) $event .= "&page_token=$nextPageToken";

        $res = $this->makeRequest(
            'POST',
            $event,
            $req
        );

        if(isset($res->status) and ($res->status === 'OK') and isset($res->result->orders))
        {
            if(!empty($res->result->orders))
            {
                $orders = [];
                foreach($res->result->orders as $key => $YandexOrder) // filter where has items
                {
                    if(!empty($YandexOrder->items))
                        $orders[] = $YandexOrder;
                }

                if(isset($res->result->paging->nextPageToken)
                    and
                    ($nextPageOrders = $this->getOrdersList($dateFrom, $res->result->paging->nextPageToken, $stopper)))
                {
                    $orders = array_merge($orders, $nextPageOrders);
                }

                return $orders;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function getOrder($systemOrderId)
    {
        $event = "/campaigns/$this->campaignId/orders/$systemOrderId.json";

        $res = $this->makeRequest(
            'GET',
            $event
        );

        if(isset($res->order))
        {
            return $res->order;
        }else{
            return false;
        }
    }

    public function getBuyerInfo($systemOrderId)
    {
        $event = "/campaigns/$this->campaignId/orders/$systemOrderId/buyer.json";
        $res = $this->makeRequest(
            'GET',
            $event
        );

        if(isset($res->status) and ($res->status === 'OK') and isset($res->result))
        {
            return $res->result;
        }else{
            return false;
        }
    }

    public function setDeliveryTrack($systemOrderId, $trackCode, $deliveryServiceId)
    {
        $event = "/campaigns/$this->campaignId/orders/$systemOrderId/delivery/track.json";
        $res = $this->makeRequest(
            'POST',
            $event,
            [
                'trackCode' => $trackCode,
                'deliveryServiceId' => $deliveryServiceId,
            ]
        );

        if(isset($res->status) and ($res->status === 'OK'))
        {
            return false; // no errors
        }else
        {
            return $res;
        }
    }

    public function getDeliveryServices()
    {
        $event = "/delivery/services.json";
        $res = $this->makeRequest(
            'GET',
            $event
        );

        if(isset($res->result) and isset($res->result->deliveryService))
        {
            return $res->result->deliveryService;
        }else
        {
            return false;
        }
    }
    public function saveDeliveryServices()
    {
        $keyCode = uniqid();
        $deliveryServices = $this->getDeliveryServices();

        foreach($deliveryServices as $DeliveryService)
        {

            $q = [
                'shop_id' => $this->shopId,
                'shop_service_id' => $DeliveryService->id,
            ];
            $ShopDeliveryService = ShopDeliveryService::firstOrNew($q);
            $ShopDeliveryService->shop_name = $DeliveryService->name;
            $ShopDeliveryService->key_code = $keyCode;
            $ShopDeliveryService->save();
        }

        ShopDeliveryService::where('key_code', '!=', $keyCode)->update(['state' => 0]);
    }

    public function deleteOutlet($outletId)
    {
        $event = "/campaigns/$this->campaignId/outlets/$outletId.json";
        $res = $this->makeRequest(
            'DELETE',
            $event
        );
        return $res;
    }

    public function removeUnknownOutlets()
    {
        $yandexOutlets = $this->getOutlets();

        $CDEKApi = new CDEKApi();
        $outlets = $CDEKApi->getDeliveryPoints();
        if(!$outlets) return false;

        $removed = 0;

        $countYandexOutlets = count($yandexOutlets);

        foreach($yandexOutlets as $key => $YandexOutlet)
        {
            dump($key . ' / ' . $countYandexOutlets);

            if($deliveryProviderId = Operations::getDeliveryProviderId($YandexOutlet->shopOutletCode))
            {
                if($deliveryProviderId === 1) // CDEK
                {
                    $found = false;
                    foreach($outlets as $cKey => $CDEKOutlet)
                    {
                        if($YandexOutlet->shopOutletCode === $CDEKOutlet->code)
                        {
                            $found = true;
                            unset($outlets[$cKey]);
                            break;
                        }
                    }

                    if(!$found)
                    {
                        $res = $this->deleteOutlet($YandexOutlet->id);
                        if($res and isset($res->status) and ($res->status !== 'OK'))
                        {
                            $removed++;
                        }else
                        {
                            //dump($res);
                        }
                    }
                }
            }
        }

        dump("Total removed $removed");
        return true;
    }
}
