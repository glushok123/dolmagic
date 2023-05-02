<?php

namespace App\Console\Api\Wildberries;

use App\Console\Api\Api;
use App\Eloquent\Order\Order;
use App\Models\Products;
use Carbon\Carbon;

class WildberriesApi extends Api
{
    public $systemId = 177; // Wildberries
    public $shopId = 177;

    public $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhY2Nlc3NJRCI6IjYzM2UzYjBhLWEyYjQtNDcxOS1hYmQ2LTcyNGI3ODE2ZjBiZiJ9.kIgUxCro1E4Fp7XF9o-c-n-WLmlGuPclv5FeCx-z9TI';
    public $host = 'https://suppliers-api.wildberries.ru';

    public $warehouseId = 237282;

    // x64 key MzNjZGI1NjAtMDMxOS00MjUzLTk3ODMtMGE0ODc3MGJhODM4

    public function __construct()
    {
        parent::__construct();

        $this->СronOptions = $this->getCronOptions();
        $this->headers = array(
            'Content-Type: application/json',
            "Authorization: $this->token"
        );
    }

    public function cardCreate($req)
    {

        /*
        dd(json_encode([
            'id' => uniqid(),
            'jsonrpc' => '2.0',
            'params' => [
                'card' => $req
            ]
        ]));
        */

        $res = $this->makeRequest(
            'POST',
            '/card/create',
            [
                'id' => uniqid(),
                'jsonrpc' => '2.0',
                'params' => [
                    'card' => $req
                ]
            ]
        );

        return $res->error ?? false;
    }

    public function cardUpdate($card): bool
    {
        $req = [
            'id' => uniqid(),
            'jsonrpc' => '2.0',
            'params' => [
                'card' => $card
            ]
        ];

        //dd(json_encode($req));

        $res = $this->makeRequest(
            'POST',
            '/card/update',
            $req
        );

        //dump($res);

        if(!isset($res->result)) dump($res);

        return isset($res->result);
    }

    public function cardList($withError = false, $offset = 0, $limit = 1000, &$cards = [])
    {
        $req = [
            'id' => uniqid(),
            'jsonrpc' => '2.0',
            'params' =>
                [
                    'query' =>
                        [
                            'offset' => $offset,
                            'limit' => $limit,
                        ]
                ]
        ];
        if($withError)
            $req['params']['withError'] = true;

        $res = $this->makeRequest(
            'POST',
            '/card/list',
            $req
        );

        //dump($res);

        if(isset($res->result->cards))
        {
            $cards = array_merge($cards, $res->result->cards);

            if(isset($res->result->cursor) and isset($res->result->cursor->total))
            {
                if(count($cards) < $res->result->cursor->total)
                {
                    $this->cardList($withError, $offset + $limit, $limit, $cards);
                }
            }
        }

        return $cards;
    }

    public function cardByImtID($imtID)
    {
        $res = $this->makeRequest(
            'POST',
            '/card/cardByImtID',
            [
                'id' => uniqid(),
                'jsonrpc' => '2.0',
                'params' => [
                    'imtID' => (int) $imtID
                ]
            ]
        );

        return $res->result->card??false;
    }


    public function warehouses()
    {
        $res = $this->makeRequest(
            'GET',
            '/api/v2/warehouses',
        );

        dd($res);
        /*
           0 => {#24
            +"name": "Склад РитмZ"
            +"id": 237282
          }

         */
    }

    public function updateStockV3($stocksToUpdate)
    {
        $chunked = array_chunk($stocksToUpdate, 1000);
        foreach($chunked as $ChunkStocksToUpdate)
        {
            //dump($ChunkStocksToUpdate);

            $res = $this->makeRequest(
                'PUT',
                "/api/v3/stocks/$this->warehouseId",
                ['stocks' => $ChunkStocksToUpdate],
            );

            if(isset($res->error) and $res->error)
            {
                dump($res);
            }
        }

        return true;
    }

    public function updatePrice($pricesToUpdate)
    {
        $total = count($pricesToUpdate);
        $parts = array_chunk($pricesToUpdate, 1000); // 1000?
        foreach($parts as $partPrices)
        {
            $res = $this->makeRequest(
                'POST',
                '/public/api/v1/prices',
                $partPrices
            );
            dump('updatePrice: Uploaded '. count($partPrices). "/$total prices");
            dump($res);
        }

        return true;
    }

    public function getOrders($dateFrom, $skip = 0)
    {
        dump(1);
        $req = [
            'date_start' => Carbon::parse($dateFrom, 'Europe/Moscow')->format('Y-m-d\TH:i:sP'),
            'take' => 1000,
            'skip' => $skip
        ];
        dump(2);

        $res = $this->makeRequest(
            'GET',
            "/api/v2/orders",
            $req
        );

        return $res->orders??[];
    }

    public function getOrdersV3($dateFrom, $next = 0, $countReq = 0)
    {
        $req = [
            'dateFrom' => Carbon::parse($dateFrom, 'Europe/Moscow')->unix(),
            'limit' => 1000,
            'next' => $next,
            //'flag' => 1 // ???
        ];

        $res = $this->makeRequest(
            'GET',
            '/api/v3/orders',
            $req
        );

        $orders = [];
        if($res and isset($res->orders) and is_array($res->orders) and !empty($res->orders))
        {
            foreach($res->orders as $SystemOrder)
            {
                if($SystemOrder->warehouseId === $this->warehouseId)
                {
                    $orders[] = $SystemOrder;
                }
            }

            /*
            if(isset($res->next) and $res->next and ($countReq < 20))
            {
                $countReq++;
                dump($countReq);
                sleep(30);
                if($nextOrders = $this->getOrdersV3($dateFrom, $res->next, ($countReq)))
                {
                    $orders = array_merge($orders, $nextOrders);
                }
            }
            */

        }


        return $orders;
    }

    public function getOrderStatusesV3($systemOrderIds)
    {
        foreach($systemOrderIds as $key => $systemOrderId)
        {
            $systemOrderIds[$key] = (int) $systemOrderId;
        }

        $req = [
            'orders' => $systemOrderIds
        ];

        $res = $this->makeRequest(
            'POST',
            '/api/v3/orders/status',
            $req
        );

        if(
            isset($res->orders)
            and is_array($res->orders)
            and (count($res->orders) > 0)
        )
        {
            return $res->orders;
        }

        return false;
    }

    public function getOrderStatusV3($systemOrderId)
    {
        $req = [
            'orders' => [$systemOrderId]
        ];

        $res = $this->makeRequest(
            'POST',
            '/api/v3/orders/status',
            $req
        );

        if(
            isset($res->orders)
            and is_array($res->orders)
            and (count($res->orders) > 0)
        )
        {
            return $res->orders[0];
        }

        return false;
    }



    public function findByUID(&$groupedOrders, $WildberriesOrder)
    {
        foreach($groupedOrders as $groupedOrder)
        {
            if($groupedOrder === $WildberriesOrder->orderUID)
                return $groupedOrder;
        }

        $WildberriesOrder->products = [];
        $groupedOrders[] = $WildberriesOrder;
        return $groupedOrders[count($groupedOrders) - 1];

    }

    public function getGroupOrderProduct($WildberriesOrder)
    {
        $Product = new \stdClass();
        $Product->id = Products::getProductByBarcode($WildberriesOrder->barcode)->id;
        $Product->quantity = 1;
        $Product->price = round($WildberriesOrder->totalPrice/100, 2);
        return $Product;
    }

    public function getOrdersStickersV3($orderIds)
    {
        $res = $this->makeRequest(
            'POST',
            "/api/v3/orders/stickers?type=png&width=40&height=30",
            [
                'orders' => $orderIds,
            ]
        );
        if(isset($res->stickers) and isset($res->stickers[0]))
        {
            if(count($orderIds) === 1)
            {
                return $res->stickers[0];
            }else
            {
                return $res->stickers;
            }

        }
        return false;
    }


    public function getOrder($systemOrderId)
    {
        if($Order = Order::where('system_order_id', $systemOrderId)->first())
        {
            $req = [
                'date_start' => Carbon::parse($Order->info->order_date_create, 'Europe/Moscow')
                    ->subDays(7)
                    ->format('Y-m-d\TH:i:sP'),
                'take' => 1,
                'skip' => 0,
                'id' => $systemOrderId
            ];

            $res = $this->makeRequest(
                'GET',
                "/api/v2/orders",
                $req
            );
        }

        return $res->orders[0]??false;
    }



    public function getOrdersList($dateFrom, $skip = 0)
    {
        $orders = $this->getOrdersV3($dateFrom);
        return $orders;
    }




    public function getSupplies($status)
    {
        $res = $this->makeRequest(
            'GET',
            "/api/v2/supplies",
            [
                'status' => $status
            ]
        );

        return $res->supplies??[];
    }

    public function getSuppliesV3()
    {
        $res = $this->makeRequest(
            'GET',
            "/api/v3/supplies",
            [
                'limit' => 1000,
                'next' => 0,
            ]
        );
        return $res->supplies??[];
    }

    public function getSuppliesOrders($supplyId)
    {
        $res = $this->makeRequest(
            'GET',
            "/api/v2/supplies/$supplyId/orders",
        );

        return $res->orders??[];
    }

    public function getSuppliesOrdersV3($supplyId, $supplyName)
    {
        $res = $this->makeRequest(
            'GET',
            "/api/v3/supplies/$supplyId/orders",
            [
                'name' => $supplyName
            ]
        );

        return $res->orders??[];
    }


    public function getAllStocksV3()
    {
        $barcodes = [];
        $wildberriesProducts = $this->contentV1CardsCursorList();
        foreach($wildberriesProducts as $WildberriesProduct)
        {
            if(isset($WildberriesProduct->sizes) and $WildberriesProduct->sizes)
            {
                foreach($WildberriesProduct->sizes as $Sizes)
                {
                    if(isset($Sizes->skus) and $Sizes->skus)
                    {
                        foreach($Sizes->skus as $barcode)
                        {
                            if($barcode)
                            {
                                $barcodes[] = $barcode;
                            }
                        }
                    }
                }
            }
        }

        return $this->getStocksV3($barcodes);
    }
    public function getStocksV3($barcodes, $limit = 1000)
    {
        $parts = array_chunk($barcodes, 1000);
        $stocks = [];

        foreach($parts as $partBarcodes)
        {
            $res = $this->makeRequest(
                'POST',
                "/api/v3/stocks/$this->warehouseId",
                [
                    'skus' => $partBarcodes,
                ]
            );

            if(isset($res->stocks) and is_array($res->stocks) and $res->stocks)
            {
                $stocks = array_merge($stocks, $res->stocks);
            }else
            {
                dump($res);
            }
        }

        return $stocks;
    }

    public function getWarehouses()
    {
        $res = $this->makeRequest(
            'GET',
            "/api/v2/warehouses"
        );

        dd($res);
        return $res->stocks??false;
    }

    public function getInfo()
    {
        $res = $this->makeRequest(
            'GET',
            "/public/api/v1/info"
        );

        return $res??false;
    }

    public function updateDiscounts($discounts)
    {
        $discountsParts = array_chunk($discounts, 1000); // 1000
        $total = count($discountsParts);
        foreach($discountsParts as $discountsPart)
        {
            $res = $this->makeRequest(
                'POST',
                "/public/api/v1/updateDiscounts",
                $discountsPart
            );

            dump('updateDiscounts: Uploaded '. count($discountsPart) . "/ $total");
            dump($res);
        }
    }

    public function revokeDiscounts($nmIds)
    {
        $nmIdsParts = array_chunk($nmIds, 500);
        $total = count($nmIds);
        foreach($nmIdsParts as $nmIdsPart)
        {
            $res = $this->makeRequest(
                'POST',
                "/public/api/v1/revokeDiscounts",
                $nmIdsPart
            );

            dump('revokeDiscounts: Uploaded '. count($nmIdsPart). "/$total");
            dump($res);
        }
    }


    public function suppliesBarcode($supplyId, $type = 'pdf')
    {
        $res = $this->makeRequest(
            'GET',
            "/api/v2/supplies/$supplyId/barcode",
            [
                'type' => $type,
            ]
        );

        return $res->file??false;
    }

    public function suppliesBarcodeV3($supplyId)
    {
        $res = $this->makeRequest(
            'GET',
            "/api/v3/supplies/$supplyId/barcode",
            [
                'type' => 'png',
                'width' => 58,
                'height' => 40,
            ]
        );

        return $res->file??false;
    }

    public function ordersStickersPDF($orderId, $type = 'code128')
    {
        $res = $this->makeRequest(
            'POST',
            "/api/v2/orders/stickers/pdf",
            [
                'id' => uniqid(),
                'jsonrpc' => '2.0',
                'orderIds' => [(int)$orderId],
                'type' => $type // code128 qr
            ]
        );
        return $res->data->file??false;
    }






    // NEW API V2




    public function contentV1CardsErrorList()
    {
        $res = $this->makeRequest(
            'GET',
            '/content/v1/cards/error/list',
        );

        return $res->data??[];

    }

    public function contentV1CardsUpload(array $cards)
    {
        $req = [$cards];
        $res = $this->makeRequest(
            'POST',
            '/content/v1/cards/upload',
            $req
        );

        if(!isset($res->error) or $res->error)
        {
            dump($res);
            return $res;
        }else
        {
            return false;
        }
    }

    public function contentV1CardsUpdate($card, $showRes = false)
    {
        $req = [$card];

        //dump(json_encode($req));
        $res = $this->makeRequest(
            'POST',
            '/content/v1/cards/update',
            $req
        );

        if($showRes) dump($res);

        if(!isset($res->error) or $res->error)
        {
            return false;
        }else
        {
            return true;
        }
    }

    public function contentV1ObjectAll()
    {
        $res = $this->makeRequest(
            'GET',
            '/content/v1/object/all',
            [
                'name' => 'Куклы',
                'top' => 50
            ]
        );

        dd($res);
    }

    public function contentV1ObjectCharacteristics($objectName = 'Игрушки')
    {
        $res = $this->makeRequest(
            'GET',
            '/content/v1/object/characteristics/'.$objectName,
            [
                'objectName' => $objectName,
            ],
            true,
            true,
            true
        );

        dd($res);
    }

    public function contentV1ObjectCharacteristicsListFilter($objectName = 'Куклы')
    {
        $res = $this->makeRequest(
            'GET',
            '/content/v1/object/characteristics/list/filter',
            [
                'name' => $objectName,
            ]
        );

        dd($res);
    }

    public function contentV1CardsFilter(array $vendorCodes)
    {
        $res = $this->makeRequest(
            'POST',
            '/content/v1/cards/filter',
            [
                'vendorCodes' => $vendorCodes,
            ]
        );

        if(isset($res->error) and !$res->error)
        {
            if(count($vendorCodes) === 1)
            {
                return $res->data[0] ?? false;
            }else
            {
                return $res->data ?? false;
            }
        }else
        {
            return false;
        }
    }

    public function contentV1MediaSave(string $vendorCode, array $data)
    {
        $res = $this->makeRequest(
            'POST',
            '/content/v1/media/save',
            [
                'vendorCode' => $vendorCode,
                'data' => $data,
            ]
        );

        if(isset($res->error) and !$res->error)
        {
            return true;
        }else
        {
            dump($res);
            return false;
        }
    }


    //https://suppliers-api.wildberries.ru/content/v1/cards/cursor/list
    public function contentV1CardsCursorList($search = false, $cursor = false, $stopper = 0)
    {
        $limit = 1000;
        $cards = [];
        if($stopper > 50)
            return $cards;

        $q = [
            'sort' => [
                'cursor' => [
                    'limit' => $limit
                ],
                'filter' => [
                    'withPhoto' => -1
                ],
            ],
        ];

        if($cursor)
        {
            $q['sort']['cursor']['updatedAt'] = $cursor->updatedAt;
            $q['sort']['cursor']['nmID'] = $cursor->nmID;
        }

        if($search)
            $q['sort']['filter']['textSearch'] = $search;

        $res = $this->makeRequest(
            'POST',
            '/content/v1/cards/cursor/list',
            $q
        );

        if(isset($res->data->cursor->total) and $res->data->cursor->total)
        {
            $cards = $res->data->cards;
            if($res->data->cursor->total >= $limit)
            {
                $cards = array_merge(
                    $cards,
                    $this->contentV1CardsCursorList(
                        $search,
                        $res->data->cursor,
                        ($stopper + 1)
                    )
                );
            }
        }

        return $cards;
    }

    public function getProductsWithoutPhoto($search = false, $cursor = false, $stopper = 0)
    {
        $limit = 1000;
        $cards = [];
        if($stopper > 50)
            return $cards;

        $q = [
            'sort' => [
                'cursor' => [
                    'limit' => $limit
                ],
                'filter' => [
                    'withPhoto' => 0
                ],
            ],
        ];

        if($cursor)
        {
            $q['sort']['cursor']['updatedAt'] = $cursor->updatedAt;
            $q['sort']['cursor']['nmID'] = $cursor->nmID;
        }

        if($search)
            $q['sort']['filter']['textSearch'] = $search;

        $res = $this->makeRequest(
            'POST',
            '/content/v1/cards/cursor/list',
            $q
        );

        if(isset($res->data->cursor->total) and $res->data->cursor->total)
        {
            $cards = $res->data->cards;
            if($res->data->cursor->total >= $limit)
            {
                $cards = array_merge(
                    $cards,
                    $this->contentV1CardsCursorList(
                        $search,
                        $res->data->cursor,
                        ($stopper + 1)
                    )
                );
            }
        }

        return $cards;
    }


    public function recommendedIns($nmId, array $recomIds)
    {
        $this->host = 'https://recommend-api.wb.ru/api';

        $res = $this->makeRequest(
            'POST',
            '/v1/ins',
            [
                [
                    'nm' => $nmId,
                    'recom' => $recomIds,
                ]
            ]
        );

        //dump('/v1/ins', $res);
    }

    public function recommendedDel($nmId, array $recomIds)
    {
        $this->host = 'https://recommend-api.wb.ru/api';

        $res = $this->makeRequest(
            'POST',
            '/v1/del',
            [
                [
                    'nm' => $nmId,
                    'recom' => $recomIds,
                ]
            ],
            true,
            true,
            true
        );

        //dump('/v1/del', $res);
    }

    public function recommendedSup($nmId)
    {
        $this->host = 'https://recommend-api.wb.ru/api';

        $res = $this->makeRequest(
            'GET',
            '/v1/sup',
            [
                'nm' => $nmId,
            ]
        );

        return $res;
    }
}
