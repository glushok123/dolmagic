<?php

namespace App\Models\Others;
use App\Console\Api\Yandex\YandexApiDBS2;
use App\Console\Api\Yandex\YandexApiFBS2;
use App\Console\Api\YandexApi;
use App\Console\Api\YandexApi3;
use App\Console\Api\YandexApiFBS;
use App\Eloquent\Order\OrdersCancellationRequest;
use App\Models\Model;
use Carbon\Carbon;

class Yandex extends Model{

    public static function getApiByShopId($shopId)
    {
        $YandexAPI = false;
        switch($shopId)
        {
            case 67: $YandexAPI = (new YandexApi()); break; // FBY
            case 71: $YandexAPI = (new YandexApi3()); break; // DBS
            case 73: $YandexAPI = (new YandexApiFBS()); break; // FBS

            case 174: $YandexAPI = (new YandexApiDBS2()); break; // DBS2
            case 176: $YandexAPI = (new YandexApiFBS2()); break; // FBS2
        }

        return $YandexAPI;
    }

    public static function getCancelReasonInfo($reason): string
    {
        switch($reason)
        {
            case 'SHOP_FAILED': return 'Магазин не может выполнить заказ';
            case 'USER_CHANGED_MIND': return 'Покупатель передумал';
            case 'USER_UNREACHABLE': return 'Невозможно связаться с покупателем';
            case 'PICKUP_EXPIRED': return 'Покупатель не забрал заказ';
            case 'ORDER_DELIVERED': return 'Заказ уже доставлен ';
        }

        return '';
    }

    public static function checkCancelRequest($shopId)
    {
        $requests = OrdersCancellationRequest
            ::whereNull('accepted')
            ->where('shop_id', $shopId)
            ->where('created_at', '>=', Carbon::now()->subMonths(2))
            ->get();
        if($requests)
        {
            $YandexApi = self::getApiByShopId($shopId);
            foreach($requests as $Request)
            {
                if($Order = $Request->Order)
                {
                    if($YandexOrder = $YandexApi->getOrder($Order->system_order_id))
                    {
                        if($YandexOrder->status === 'CANCELLED')
                        {
                            $Request->accepted = 1;
                            $Request->user_id = 0;
                            $Request->save();
                        }
                    }
                }

            }


        }

    }
}


