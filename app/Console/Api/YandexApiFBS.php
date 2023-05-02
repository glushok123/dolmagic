<?php

namespace App\Console\Api;

use App\Eloquent\Products\Product;
use App\Models\Products;
use Carbon\Carbon;

class YandexApiFBS extends YandexApi
{
    // FBS
    // https://yandex.ru/dev/market/partner-marketplace/doc/dg/reference/post-campaigns-id-offer-mapping-entries-updates.html
    public $systemId = 76;
    public $shopId = 73;
    public $appSecret = 'ed88b6a3b9c41537e27a38612b55bfd7';
    public $campaignId = '22133873';
    public $yaWarehouseId = 104203;
    public $host = 'https://api.partner.market.yandex.ru/v2/campaigns/22133873';

    //https://oauth.yandex.ru/verification_code?response_type=token&client_id=f7594d2d0c6c43818c4eee7fb1932865

    public function __construct()
    {
        parent::__construct();

        $this->headers = array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: OAuth oauth_token="AQAAAAAcHKaTAAaoXO23XJD8r0potCCjIVnogMM", oauth_client_id="f7594d2d0c6c43818c4eee7fb1932865"'
        );
    }

    public function getOrder($systemOrderId)
    {
        $event = "/orders/$systemOrderId.json";

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


    public function getOrderLabel($systemOrderId)
    {
        $event = "/orders/$systemOrderId/delivery/labels.json";

        $res = $this->makeRequest(
            'GET',
            $event,
            NULL,
            NULL,
            false
        );
        header("Content-type: application/pdf");
        print_r($res);
        die();
    }

    public function getOrderPackLabel($systemOrderId, $cargoNumber)
    {
        $SystemOrder = $this->getOrder($systemOrderId);
        $Shipment = $SystemOrder->delivery->shipments[0];

        if(!isset($Shipment->boxes))
        {
            dd('Наклейка ещё не создана!');
        }

        foreach($Shipment->boxes as $Box)
        {
            if($Box->fulfilmentId === $cargoNumber)
            {
                $event = "/orders/$systemOrderId/delivery/shipments/$Shipment->id/boxes/$Box->id/label.json";

                $res = $this->makeRequest(
                    'GET',
                    $event,
                    NULL,
                    NULL,
                    false
                );
                header("Content-type: application/pdf");
                print_r($res);
                die();
            }
        }
        dd('Наклейка не найдена!');
    }

    public function getDoubleProductsInCard()
    {
        $YandexProducts = $this->getProducts();
        $products = [];

        if(count($YandexProducts) === 0) dd('Ошибка маркета - нет товаров!');

        foreach($YandexProducts as $YandexProduct)
        {


            if(isset($YandexProduct->mapping) and isset($YandexProduct->mapping->marketSku))
            {
                if(!isset($products[$YandexProduct->mapping->marketSku]))
                {
                    $products[$YandexProduct->mapping->marketSku] = new \stdClass();
                    $products[$YandexProduct->mapping->marketSku]->count = 0;
                    $products[$YandexProduct->mapping->marketSku]->skus = [];
                }

                $products[$YandexProduct->mapping->marketSku]->count++;
                $products[$YandexProduct->mapping->marketSku]->skus[] = $YandexProduct->offer->shopSku;
            }
        }

        foreach($products as $marketSku => $Stat)
        {
            if($Stat->count < 2)
            {
                unset($products[$marketSku]);
            }
        }

        return $products;
    }



    public function offersStocks($skus)
    {
        $partSkus = array_chunk($skus, 2000);

        foreach($partSkus as $PartSku)
        {
            $res = $this->makeRequest(
                'PUT',
                '/offers/stocks.json',
                [
                    'skus' => $PartSku
                ]
            );
            dump($res);
        }
    }

    public function updateStocks()
    {
        $yaProducts = (new YandexApiFBS())->getProducts();
        $toUpdates = [];
        $total = count($yaProducts);

        foreach($yaProducts as $key => $YaProduct)
        {
            dump("$key / $total");

            $ToUpdate = new \stdClass();
            $ToUpdate->sku = $YaProduct->offer->shopSku;
            $ToUpdate->warehouseId = $this->yaWarehouseId;
            $ToUpdate->items = [];

            $ItemToUpdate = new \stdClass();
            $ItemToUpdate->type = 'FIT';
            $ItemToUpdate->updatedAt = Carbon::now('Europe/Moscow')->toIso8601String();
            $ItemToUpdate->count = 0;

            if($Product = Product::where('sku', $ToUpdate->sku)->where('yandex_not_upload', 0)->first())
            {
                $Stop = Products::getSystemsProductsStopResult($Product, $this->shopId);
                if(!$Stop->stock)
                {
                    $ItemToUpdate->count = $Product->shopAmounts($this->shopId)->amounts->balance??0;
                }
            }

            $ToUpdate->items[] = $ItemToUpdate;
            $toUpdates[] = $ToUpdate;
        }

        if(count($toUpdates) > 0)
            $this->offersStocks($toUpdates);
    }
}
