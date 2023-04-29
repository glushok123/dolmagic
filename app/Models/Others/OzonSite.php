<?php

namespace App\Models\Others;

use App\Models\Model;
use App\Models\Shops\Shops;

class OzonSite extends Model
{

    public static function requestToSite($shopId, $url, $body, $sleeping = true): object
    {
        if($sleeping) sleep(rand(1, 2));

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
        ]);

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $jsonDataEncoded = json_encode($body, JSON_UNESCAPED_UNICODE);

        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonDataEncoded);

        if(!$Shop = Shops::getShop($shopId)) Log::crash('Unknown shop id');
        if(!$shopInternalId = $Shop->internal_id) Log::crash('Unknown shopInternalId');
        if(!$shopInternalAccess = $Shop->internal_access)  Log::crash('Unknown shopInternalAccess');

        $headers = array(
            'Content-Type: application/json',
            "accesstoken: $shopInternalAccess",
            "x-o3-company-id: $shopInternalId",

            // hiding tools
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.51 Safari/537.36',
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if($http_code !== 200)
        {
            dump($response);
            Log::crash('Unknown response CODE');
        }
        curl_close($curl);
        return (object) [
            'code' => $http_code,
            'response' => json_decode($response),
        ];
    }

    public static function getSkuSettings($shopId, array $ozonProductIds)
    {
        $url = 'https://seller.ozon.ru/api/site/pf-automod-api/sc/v2/get_sku_settings';

        if(empty($ozonProductIds)) Log::crash('Doesnt have ids');

        $req = ['productId' => $ozonProductIds];
        $res = self::requestToSite($shopId, $url, $req);

        if(!isset($res->response->settings)) Log::crash('Doesnt have response settings');
        return $res->response->settings;
    }

    public static function getProductsIdsFromPrices($ozonProductsPrices)
    {
        $productIds = [];
        foreach($ozonProductsPrices as $OzonProductPriceInSale)
        {
            $productIds[] = $OzonProductPriceInSale->product_id;
        }

        return $productIds;
    }

    public static function getProductSettingFromArray($productId, $productsSkuSettings)
    {
        foreach($productsSkuSettings as $key => $ProductsSkuSetting)
        {
            if($key == $productId) return $ProductsSkuSetting;
        }
        return false;
    }

    public static function updateProductMinimumPriceForApproval($shopId, $productId, $minimumApprovalPrice)
    {
        $url = 'https://seller.ozon.ru/api/site/pf-automod-api/sc/v2/update_sku_settings';
        $req = ['settings' => [
            $productId => [
                'price' => $minimumApprovalPrice
            ]
        ]];

        $res = self::requestToSite($shopId, $url, $req);
        if($res->code !== 200)  Log::crash('Unknown response CODE');
        /*
         * there is empty response
        if($res->response)
        {
            dump($res->response);
            Log::crash('Unknown response RESULT'); // ozon returns always {}
        }
        */
    }

    public static function updateMinimumPriceForApproval($shopId)
    {
        $OzonApi = Ozon::getOzonApiByShopId($shopId);
        if(!$OzonApi) Log::crash('OzonApi undefended');

        $ozonProductsPricesInSale = $OzonApi->getPricesV4([
            'visibility' => 'IN_SALE'
        ]);

        $totalCount = count($ozonProductsPricesInSale);
        $now = 0;

        $partProducts = array_chunk($ozonProductsPricesInSale, 20);
        foreach($partProducts as $partProductPrices)
        {
            $productIds = self::getProductsIdsFromPrices($partProductPrices);
            $productsSkuSettings = self::getSkuSettings($shopId, $productIds);
            foreach($partProductPrices as $OzonProductPriceInSale)
            {
                Log::counting($now, $totalCount, $OzonProductPriceInSale->offer_id, false);
                $now++;

                $minimumApprovalPrice = 0;
                if(
                    isset($OzonProductPriceInSale->price->marketing_price)
                    and $marketingPrice = (float) $OzonProductPriceInSale->price->marketing_price
                ) $minimumApprovalPrice = round($marketingPrice * 0.9495);

                if($Setting = self::getProductSettingFromArray($OzonProductPriceInSale->product_id, $productsSkuSettings))
                {
                    if($setPrice = $Setting->price)
                    {
                        if($setPrice == $minimumApprovalPrice)
                        {
                            continue; // no update id all ok!
                        }
                    }
                }

                if($minimumApprovalPrice)
                {
                    self::updateProductMinimumPriceForApproval($shopId, $OzonProductPriceInSale->product_id, $minimumApprovalPrice);
                }
            }
        }

        Log::success('Update are successfully');
    }
}


