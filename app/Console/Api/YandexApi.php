<?php

namespace App\Console\Api;

use App\Eloquent\Products\Product;
use App\Eloquent\Products\TypeShopProduct;
use App\Eloquent\System\SystemsOrdersStatus;
use App\Models\Others\Unloading\Unloading;
use App\Models\Products;
use Carbon\Carbon;

class YandexApi extends Api
{
    // FBY https://partner.market.yandex.ru/
    // https://yandex.ru/dev/market/partner-marketplace/doc/dg/concepts/about.html
    public $systemId = 68; // Beru/YandexMarketPlace = 68
    public $shopId = 67;
    public $appSecret = 'ed88b6a3b9c41537e27a38612b55bfd7';
    public $campaignId = '21633736';
    public $host = 'https://api.partner.market.yandex.ru/v2/campaigns/21633736';

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
        $req = [
            'orders' => [$systemOrderId]
        ];

        $event = '/stats/orders.json?limit=200';

        $res = $this->makeRequest(
            'POST',
            $event,
            $req
        );

        if(isset($res->status) and ($res->status === 'OK') and isset($res->result->orders) and !empty($res->result->orders))
        {
            return $res->result->orders[0];
        }else{
            return false;
        }
    }

    public function getOrdersList($dateFrom, $nextPageToken = false, $stopper = 0)
    {

        $stopper++;
        if($stopper > 50)
        {
            $this->log('error', 'getOrdersList', 'Problem with pagination');
            die();
        }
        $req = [
            'updateFrom' => Carbon::parse($dateFrom)->format('Y-m-d')
            /*
            'status' =>
            [
                //'CANCELLED_BEFORE_PROCESSING', // — заказ отменен до начала его обработки.
                //'CANCELLED_IN_DELIVERY', // — заказ отменен во время его доставки.
                //'CANCELLED_IN_PROCESSING', // — заказ отменен во время его обработки.
                'DELIVERY', // — заказ передан службе доставки.
                'DELIVERED', // — заказ доставлен.
                'PICKUP', // — заказ доставлен в пункт выдачи.
                'PROCESSING', // — заказ в обработке.
                'UNKNOWN', // — неизвестный статус заказа.
            ]
            */
        ];

        $event = '/stats/orders.json?limit=200';
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


    public function getProductsCards(&$products)
    {
        $req = ['offers' => []];

        foreach($products as $key => $Product)
        {
            $Offer = new \stdClass();
            $Offer->shopSku = $Product->sku;

            $badWords = array("Кукла ", "Набор кукол ", ' набор кукол', "(Mattel) для кукол", "(Mattel)", "кукол-", "Набор одежды для ", ' - Игровой набор', ", Игровой набор");
            $clearName = str_ireplace($badWords, "", $Product->name_ru);

            $Offer->name = "{$Product->type->name} {$Product->manufacturer->name} {$Product->group->name} {$clearName}";
            $Offer->category = $Product->type->market_category;
            $Offer->vendor = $Product->manufacturer->name;
            $Offer->vendorCode = $Product->sku;
            //$Offer->price = (double) $Product->temp_price;


            $req['offers'][] = $Offer;

            if(count($req['offers']) === 500 or ((count($products) - 1) === $key))
            {
                $res = $this->makeRequest(
                    'POST',
                    '/offer-mapping-entries/suggestions.json',
                    $req
                );

                if(isset($res->result->offers) and $res->status !== 'OK')
                {
                    $this->log('error', 'getProductsCards', 'code 1', $req, $res);
                    die();
                }

                foreach($products as $Product2)
                {
                    foreach($res->result->offers as $ResOffer)
                    {
                        if($Product2->sku === $ResOffer->shopSku)
                        {
                            $Product2->offer = $ResOffer;

                            if(isset($Product2->offer->marketSku)) // saving mapping in base
                            {
                                $TypeShopProduct = TypeShopProduct::firstOrNew([
                                    'type_shop_id' => $this->shopId,
                                    'product_id' => $Product2->id
                                ]);
                                $TypeShopProduct->shop_product_id = $Product2->offer->marketSku;
                                $TypeShopProduct->save();
                            }
                        }
                    }
                }

                $req = ['offers' => []]; // clearing
            }
        }
    }

    public function updateProducts($products = [], $successLog = false, $test = false) // uploading and updating products
    {
        $start = microtime(true);
        $shopId = $this->shopId;

        if(!$products)
        {
            $products = Product::where([
                ['archive', 0],
                ['state', '>', '-1'],
                ['temp_price', '>', 0]
            ])
                ->where('yandex_not_upload', 0)
                ->whereHas('images')
                ->whereDoesntHave('systemsProductsStops', function($q) use ($shopId)
                {
                    $q->where(function($q) use ($shopId)
                    {
                        $q
                            ->whereNull('orders_type_shop_id')
                            ->orWhere('orders_type_shop_id', $shopId);
                    })
                        ->where('stop_stock', 1);
                })
                ->get();
        }

        if(count($products) === 0)
        {
            $this->log('info', 'updateProducts', 'Nothing to upload');
            dd('Nothing to upload');
        }

        $productsCount = count($products);
        dump("To update $productsCount");


        $this->getProductsCards($products);

        $req = new \stdClass();
        $req->offerMappingEntries = []; // max 500 (!400)


        foreach($products as $key => $Product)
        {
            dump("$key of $productsCount");

            $Offer = new \stdClass();
            $Offer->shopSku = $Product->sku;

            $badWords = array("Кукла ", "Набор кукол ", ' набор кукол', "(Mattel) для кукол", "(Mattel)", "кукол-", "Набор одежды для ", ' - Игровой набор', ", Игровой набор");
            $clearName = str_ireplace($badWords, "", $Product->name_ru);

            if($newTitle = Unloading::getProductTitle($Product, $this->shopId))
            {
                $Offer->name = $newTitle;
            }else
            {
                if(isset($Product->offer->marketModelName))
                {
                    $Offer->name = $Product->offer->marketModelName;
                }else{
                    // $Offer->name = "{$Product->type->name} {$Product->manufacturer->name} {$Product->group->name} {$clearName}";

                    $Offer->name = "{$Product->type->name} {$clearName}";

                }
            }

            if($newCategory = Unloading::getProductCategory($Product, $this->shopId))
            {
                $Offer->category = $newCategory;
            }else
            {
                $Offer->category = $Product->category->market_id;
                /*
                if(isset($Product->offer->marketCategoryName))
                {
                    $Offer->category = $Product->offer->marketCategoryName;
                }else{
                    //$Offer->category = $Product->type->market_category;
                    $Offer->category = $Product->category->market_id;
                }
                */

            }

            $Offer->manufacturer = $Product->manufacturer->name;
            $Offer->manufacturerCountries = ['Китай'];

            $WeightDimensions = new \stdClass();

            $WeightDimensions->length = $Product->BoxSizes->valueLength;
            $WeightDimensions->width = $Product->BoxSizes->valueWidth;
            $WeightDimensions->height = $Product->BoxSizes->valueHeight;
            $WeightDimensions->weight = $Product->BoxSizes->valueWeightKg;
            $Offer->weightDimensions = $WeightDimensions;

            $images = Products::getShopImages($Product, $this->shopId);
            $pictures = [];
            foreach($images as $ProductImage)
            {
                $pictures[] = $ProductImage->url;
            };
            $Offer->pictures = $pictures;
            $Offer->urls = $pictures;

            $Offer->vendor = $Product->manufacturer->name;
            $Offer->vendorCode = $Product->sku;
            //  barcodes
            $Offer->barcodes = $Product->barcodes;

            $Offer->description = strip_tags(trim($Product->temp_short_description), '<br>');
            if(mb_strlen($Offer->description) === 0) $Offer->description = 'Описание на данный момент отсутствует.';

            $Offer->shelfLife = new \stdClass();
            $Offer->shelfLife->timePeriod = Unloading::getPeriodOfValidityDays(true);
            $Offer->shelfLife->timeUnit = 'YEAR';

            if(!empty($Product->getShopOption($this->shopId)->archive))
            {
                $Offer->availability = 'DELISTED'; // архив
            }else{
                $Offer->availability = 'ACTIVE'; // поставки будут
            }

            $Mapping = new \stdClass();
            $mapping = false;
            if(isset($Product->offer->marketSku))
            {
                $mapping = true;
                $Mapping->marketSku = $Product->offer->marketSku;
            }

            $ReqOffer = new \stdClass();
            $ReqOffer->offer = $Offer;
            if($mapping) $ReqOffer->mapping = $Mapping;

            // check if no need to export
            if(empty($Product->getShopOption($this->shopId)->export_stop))
            {
                $req->offerMappingEntries[] = $ReqOffer;
            }

            if((count($req->offerMappingEntries) === 1) or (($productsCount - 1) === $key))
            {
                $event = '/offer-mapping-entries/updates.json';
                if($test)
                {
                    //$event .= '?dbg=8E00000103761A1B';
                }
                $res = $this->makeRequest(
                    'POST',
                    $event,
                    $req
                );

                if($test)
                {
                    var_dump($req);
                    var_dump($res);
                }

                $ExecutionTime = ' Script execution time '.round(microtime(true) - $start, 4).'sec';
                if(isset($res->status) and ($res->status === 'OK'))
                {
                    if($successLog)
                    {
                        $this->log('success', 'updateProducts', 'Upload/updated: '.count($products).$ExecutionTime, $req, $res);
                    }
                }else{
                    $this->log('error', 'updateProducts', 'Upload/updated: '.count($products).$ExecutionTime, $req, $res);
                }

                $req->offerMappingEntries = [];
            }

            print_r($key.' of '.$productsCount."\r");
        }
    }

    public function offerPriceUpdates($offers)
    {
        $res = $this->makeRequest(
            'POST',
            '/offer-prices/updates.json',
            [
                'offers' => $offers
            ]
        );
        if(isset($res->status) and ($res->status === 'OK'))
        {
        }else{
            dump($res);
        }
    }

    public function updatePrices($sku = false, $countSend = 100, $periodSend = 30) //updating prices
    {
        $offers = [];
        $yandexProducts = $this->getProducts(false, false, false, $sku);
        $skuList = [];

        if(count($yandexProducts) === 0)
        {
            dump('Nothing to upload');
            return false;
        }

        $productsCount = count($yandexProducts);

        foreach($yandexProducts as $key => $YandexProduct)
        {
            if(isset($YandexProduct->mapping) and isset($YandexProduct->mapping->marketSku))
            {


                if($Product = Products::getProductBy('sku', $YandexProduct->offer->shopSku))
                {
                    $Stop = Products::getSystemsProductsStopResult($Product, $this->shopId);
                    if(!$Product->archive and !$Stop->price) // if not archive and manual changing price
                    {
                        if($Product->temp_price > 0)
                        {
                            $ActualPrice = $Product->ActualPrice($this->shopId);

                            $Offer = new \stdClass();
                            $Offer->marketSku = $YandexProduct->mapping->marketSku;


                            $Price = new \stdClass();
                            $Price->currencyId = 'RUR'; // default
                            $Price->value = (double) $ActualPrice->price;
                            if($ActualPrice->old_price)
                            {
                                $oldPrice = $ActualPrice->old_price;
                                $discount = $ActualPrice->old_price / $ActualPrice->price * 100;
                                if($discount < 5) $oldPrice = $Price->value + $Price->value * 6 / 100 + 0.05;
                                if($discount > 75) $oldPrice = $Price->value + $Price->value * 74 / 100 + 0.75;

                                $Price->discountBase = (double) $oldPrice;
                            }

                            $Offer->price = $Price;

                            if(!in_array($YandexProduct->mapping->marketSku, $skuList))
                            {
                                $offers[] = $Offer;
                                $skuList[] = $YandexProduct->mapping->marketSku; // unique check
                            }else{
                                //$this->log('error', 'updatePrices', "Double market sku: {$YandexProduct->mapping->marketSku} $Product->sku");
                            }
                        }else{
                            //$this->log('error', 'updatePrices', "Product price equals 0: {$YandexProduct->mapping->marketSku} $Product->sku");
                        }
                    }
                }else{
                    //$this->log('error', 'updatePrices', "Unknown Product in Yandex sku: {$YandexProduct->offer->shopSku}");
                }
            }
        }

        if(count($offers) > 0)
        {
            $partOffers = array_chunk($offers, $countSend);
            $totalParts = count($partOffers);
            foreach($partOffers as $key => $PartOffer)
            {
                dump("part sending $key of $totalParts");

                $this->offerPriceUpdates($PartOffer);

                if($key !== ($totalParts - 1))
                {
                    dump('sleep 30 sec');
                    sleep($periodSend);
                }
            }
        }

        var_dump("End $productsCount");
    }

    public function getProducts($status = false, $availability = false, $pageToken = false, $shopSku = false): array
    {
        $limit = 100;

        $req = new \stdClass();
        $req->limit = $limit;
        if($status) $req->status = $status; // READY — товар прошел модерацию.
        if($availability) $req->availability = $availability; //ACTIVE — поставки будут. INACTIVE — поставок не будет: товар есть на складе, но вы больше не планируете его поставлять.
        if($pageToken) $req->page_token = $pageToken;
        if($shopSku) $req->shop_sku = $shopSku;


        sleep(1);
        $res = $this->makeRequest(
            'GET',
            '/offer-mapping-entries.json',
            $req,
        );

        if(isset($res->status) and ($res->status === 'OK'))
        {
            $products = $res->result->offerMappingEntries;

            if((count($res->result->offerMappingEntries)) === $limit and isset($res->result->paging->nextPageToken) and $res->result->paging->nextPageToken)
            {
                $nextPageProducts = $this->getProducts($status, $availability, $res->result->paging->nextPageToken);
                if($nextPageProducts) $products = array_merge($products, $nextPageProducts);
            }

            return $products;
        }else{
            $this->log('error', 'getProducts', 'Paginating error', $req, $res);
            dump($res);
            dd('Ошибка маркета - он недоступен.');
        }
    }

    public function getPrices()
    {
        $res = $this->makeRequest(
            'GET',
            '/offer-prices.json'
        //$req
        );

        dd($res);
    }

    public function getStatsSkus($skus)
    {
        $stats = [];


        $chunksSkus = array_chunk($skus, 100);

        foreach($chunksSkus as $chunkSkus)
        {
            $res = $this->makeRequest(
                'POST',
                '/stats/skus.json',
                [
                    'shopSkus' => $chunkSkus
                ]
            );

            if(isset($res->status) and ($res->status === 'OK'))
            {
                if(isset($res->result->shopSkus))
                {
                    if(count($skus) === 1)
                    {
                        $stats = $res->result->shopSkus[0];
                    }else{
                        $stats = array_merge($stats, $res->result->shopSkus);
                    }
                }
            }else
            {
                $this->log('error', 'getStatsSkus', 'Error when get products', NULL, $res);

            }
        }

        return $stats;
    }

    public function changeOrderStatus($Order, $systems_orders_status_id, $deliveredDate = false)
    {
        $body = new \stdClass();
        $body->order = new \stdClass();
        $body->order->status = SystemsOrdersStatus::where('id', $systems_orders_status_id)->firstOrFail()->alias;

        if(mb_stripos($body->order->status, '-') !== false)
        {
            $arrStatus = explode('-', $body->order->status);
            $body->order->status = $arrStatus[0];
            $subStatus = $arrStatus[1];
            if($subStatus) $body->order->substatus = $subStatus;
        }

        $event = "/orders/$Order->system_order_id/status.json";
        $res = $this->makeRequest(
            'PUT',
            $event,
            $body
        );

        $error = false;

        if(isset($res->status) and ($res->status === 'ERROR'))
        {
            $this->log('error', 'changeOrderStatus', 'Error when change status', $body, $res);
            $error = isset($res->errors[0]->message)?$res->errors[0]->message:'changeOrderStatus';
        }

        return $error;
    }

    public function getShipmentId($systemOrderId)
    {
        $SystemOrder = $this->getOrder($systemOrderId);

        if($SystemOrder)
        {
            return $SystemOrder->delivery->shipments[0]->id??false;
        }else
        {
            return false;
        }
    }

    public function updateOrderBoxes($Order) // now Only Yandex FBS
    {
        $error = false;

        $shipmentId = $this->getShipmentId($Order->system_order_id);

        if($shipmentId)
        {
            $Body = new \stdClass();
            $Body->boxes = [];

            foreach($Order->packs as $Pack)
            {
                $Box = new \stdClass();
                $Box->fulfilmentId = $Pack->CargoNumber;
                $Box->weight = $Pack->weight;
                $Box->width = $Pack->width;
                $Box->height = $Pack->height;
                $Box->dept = $Pack->dept;

                $Body->boxes[] = $Box;
            }

            $event = "/orders/$Order->system_order_id/delivery/shipments/$shipmentId/boxes.json";

            $Res = $this->makeRequest(
                'PUT',
                $event,
                $Body
            );

            if(isset($Res->status) and ($Res->status === 'ERROR'))
            {
                $this->log('error', 'updateOrderBoxes', 'Error when update', $Body, $Res);
                $error = $Res->errors[0]->message ?? 'updateOrderBoxes';
            }
        }else
        {
            $error = 'Не найден номер отправления';
        }

        return $error;
    }

    public function getDeliveryServices()
    {
        // GET /delivery/services

        $event = "/delivery/services.json";
        $res = $this->makeRequest(
            'GET',
            $event,
        );

        dd($res);
    }





}
