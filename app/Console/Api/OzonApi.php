<?php

namespace App\Console\Api;

use App\Eloquent\Order\Order;
use App\Eloquent\Other\Ozon\OzonFbsReturn;
use App\Eloquent\Other\OzonAction;
use App\Eloquent\Other\OzonPremiumPrice;
use App\Eloquent\Products\Product;
use App\Eloquent\Products\ProductShopCategoriesAttribute;
use App\Eloquent\Products\ProductShopCategory;
use App\Eloquent\Products\TypeShopProduct;
use App\Eloquent\Sales\Sale;
use App\Eloquent\Shops\Products\ShopProductsCategoriesToAttribute;
use App\Eloquent\Shops\Products\ShopProductsCategory;
use App\Eloquent\Shops\Products\ShopProductsCategoriesAttribute;
use App\Eloquent\System\OzonIndexPrice;
use App\Eloquent\System\SystemsProductsStop;
use App\Eloquent\System\SystemsTransaction;
use App\Eloquent\System\SystemsTransactionsType;
use App\Models\Orders;
use App\Models\Others\Ozon;
use App\Models\Prices\Price;
use App\Models\Products;
use App\Models\Shops\ShopProducts;
use App\Models\Shops\Shops;
use App\Models\Users\Notifications;
use Carbon\Carbon;

class OzonApi extends Api
{

    public $shopId;
    public $systemId = 3; // OZON = 3
    public $host = 'https://api-seller.ozon.ru'; // Host
    //public $host = 'http://api-seller.ozon.ru'; // Host
    public $vat = '0'; // НДС, возможные значения: 0, 0.1, 0.2
    public $deliverySchema = 'fbs';
    public $importVat = '0'; // default to all


    public $Warehouses = array(
        'Stavropol' => array(
            'id' => 1,
            'token' => '35630',
            'pass' => '42e79879-d45e-45a2-af5f-fa9a0faf461d',
            'importUpPrice' => '200',
            'typeShopId' => 1,

            'ozonDeliveryWarehouseId' => '17331638847000', // Is it doesn't need?
            'crmDeliveryWarehouseId' => false,  // Is it doesn't need?
        ),
        'Moscow' => array(
            'id' => 2,
            'token' => '3058',
            'pass' => '204ecf89-d7c9-468a-b9ba-4a186f211f4f',
            'importUpPrice' => '300',
            'typeShopId' => 2,
            'ozonDeliveryWarehouseId' => '15682964066000',  // Is it doesn't need?
            'crmDeliveryWarehouseId' => '21202719649000',  // Is it doesn't need?
        )
    );

    //Buffers
    public $productsList = array();
    public $productsStocks = array();
    public $productsPrices = array();
    public $productsPricesV2 = array();
    public $productsPricesV3 = array();
    public $productsPricesV4 = array();
    public $storeProductsList = array();
    public $insalesProducts = array();
    public $ritmzProducts = array();

    public function __construct($WarehouseAlias, $showLog = false)
    {
        $this->Warehouse = $this->Warehouses[$WarehouseAlias];

        if(!$this->shopId) $this->shopId = $this->Warehouse['typeShopId'];

        parent::__construct($showLog);

        $this->headers = array(
            'Content-Type: application/json',
            'Client-Id: ' . $this->Warehouse['token'],
            'Api-Key: ' . $this->Warehouse['pass']
        );
    }

    public function getOrdersV3($date_from, $offset = 0)
    {
        $limit = 50;
        $req = [
            'with' => ['analytics_data' => true],
            'dir' => 'asc',
            'filter' => [
                'since' =>  Carbon::parse($date_from, 'Europe/Moscow')->format('Y-m-d\TH:i:s.000\Z'),
                'to' =>     Carbon::now()->setTimezone('Europe/Moscow')->format('Y-m-d\TH:i:s.000\Z')
            ],
            'limit' => $limit,
            'offset' => $offset
        ];

        $res = $this->makeRequest(
            'POST',
            "/v3/posting/fbs/list",
            $req
        );

        if(isset($res->error) or (!isset($res->result)))
        {
            $code = 'POST /v3/posting/fbs/list : code 1';
            $this->log('error', 'getOrdersV3', $code, $req, $res);
            die($code);
        }

        $orders = $res->result->postings??[];
        if(isset($res->result->has_next) and $res->result->has_next)
        {
            $orders = array_merge(
                $orders,
                $this->getOrdersV3($date_from, ($offset + $limit))
            );
        }

        return $orders;
    }

    public function getOrder($orderId, $withAnalyticsData = true, $withFinancialData = false) // version 3 // V3
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
            "/v3/posting/fbs/get",
            $req
        );

        if(!isset($res->result) or isset($res->error))
        {
            $this->log('error', 'getOrder', 'POST /v3/posting/fbs/get code 1', $req, $res);
            dump($res);
            return false;
        }else{
            return $res->result;
        }
    }

    public function tempClearAttribute(&$productsAttributes) // error from 2022-08-19 on something products
    {
        foreach($productsAttributes as $ProductsAttribute)
        {
            $attributeIds = [];
            foreach($ProductsAttribute->attributes as $key => $Attribute)
            {
                if(!in_array($Attribute->attribute_id, $attributeIds))
                {
                    $attributeIds[] = $Attribute->attribute_id;
                }else
                {
                    unset($ProductsAttribute->attributes[$key]);
                }
            }

            $ProductsAttribute->attributes = array_values($ProductsAttribute->attributes);
        }
    }

    public function getAttributesV3(array $filter, $first = true, $limit = 1000, $lastId = false)
    {
        $attributes = [];
        if(isset($filter['offer_id']))
        {
            $arrayChunks = array_chunk($filter['offer_id'], 1000);

            foreach($arrayChunks as $arrayChunk)
            {
                $attributes = array_merge($attributes, self::getAttributesV3Stack(
                    ['offer_id' => $arrayChunk],
                    $first,
                    $limit,
                    $lastId
                ));
            }
        }

        $this->tempClearAttribute($attributes);


        return $attributes;
    }

    public function getAttributesV3Stack(array $filter, $first = true, $limit = 1000, $lastId = false)
    {
        $attributes = [];

        $req = [
            'filter' => $filter,
            'limit' => $limit,
        ];

        if($lastId) $req['last_id'] = $lastId;

        $res = $this->makeRequest(
            'POST',
            "/v3/products/info/attributes",
            $req
        );

        if(!isset($res->result))
        {
            $this->log('error', 'getAttributes v3', 'Unknown result parameter', $req, $res);
            return $attributes;
        }

        if($res->result)
        {
            $attributes = $res->result;

            if(!$first and isset($res->last_id) and $res->last_id)
                $attributes = array_merge($attributes, $this->getAttributesV3Stack(
                    $filter, $first, $limit, $res->last_id
                ));
        }

        return $first?$attributes[0]:$attributes;
    }

    public $attributes = [];
    public function calcDeliveryPrice(Product $Product, $productPrice = false)
    {
        if(isset($this->attributes[$Product->sku]))
        {
            $Attributes = $this->attributes[$Product->sku];
        }else{
            $Attributes = $this->getAttributesV3(['offer_id' => [$Product->sku]]);
            $this->attributes[$Product->sku] = $Attributes;
        }

        $liters =
            ($Attributes->height??$Product->box_height)
            * ($Attributes->depth??$Product->box_width)
            * ($Attributes->width??$Product->box_length)
            / 1000;

        if($Attributes->dimension_unit === 'mm') $liters = $liters / 1000; // mm -> cm

        $volumeWeight = $liters/5;

        $weight = $Attributes->weight;
        if($Attributes->weight_unit === 'g') $weight = $weight / 1000; // g -> kg

        if($volumeWeight < $weight) $volumeWeight = $weight;
        $volumeWeight = round($volumeWeight, 1);

        $res = new \stdClass();
        $price = $productPrice?$productPrice:$Product->temp_price;
        $res->deliveryPrice = 45 + ($volumeWeight * 19) + ($price * 4.4 / 100);
        $res->deliveryPriceText = "45 + ($volumeWeight * 19) + ($price * 4.4 / 100)";
        return $res;

        //45 руб. (приём/обработка) + объёмный вес (он есть в админке Оз) * 19 + Стоимость * 4,4% = Расчётная стоимость доставки
    }

    public function preloadAttributes(array $productsIds)
    {
        $attributes = $this->getAttributesV3(['product_id' => $productsIds], false);
        foreach($attributes as $Attribute)
        {
            $this->attributes[$Attribute->offer_id] = $Attribute;
        }
    }

    public function getOrdersList($date_from)
    {
        return $this->getOrdersV3($date_from);
    }

    public function getPackageLabel(){
        $req = ['posting_number' => ['44773328-0001-1']];

        $res = $this->makeRequest(
            'POST',
            "/v2/posting/fbs/package-label",
            $req,
            NULL,
            false
        );

        return $res;
    }


    //Products
    public $productsListV2 = array();
    public function getProductsListV2($filter = false, $reload = false, $lastId = false, $call = 1)
    {
        if(empty($this->productsListV2) or $reload)
        {
            $productsListV2 = [];
            if($reload) $this->productsListV2 = [];

            $req = [
                'limit' => 1000,
            ];

            $req['filter'] = $filter?:['visibility' => 'ALL'];
            if($lastId) $req['last_id'] = $lastId;

            $res = $this->makeRequest(
                'POST',
                "/v2/product/list",
                $req
            );

            if(!isset($res->result) or !isset($res->result->items))
            {
                $this->log('error', 'getProductsListV2', 'Unknown result parameter', $req, $res);
                return $productsListV2;
            }

            $productsListV2 = $res->result->items;
            if(isset($res->result->last_id) and $res->result->last_id)
            {
                $productsListV2 = array_merge($productsListV2, $this->getProductsListV2(
                    $filter, false, $res->result->last_id, $call+1
                ));
            }

            if($call === 1)
            {
                $this->productsListV2 = $productsListV2;
            }else
            {
                return $productsListV2;
            }
        }

        return $this->productsListV2;
    }

    public function ozonProductsToImport($all = false){
        $productsList = Product::where([
            ['state', '>', '-1'],
            ['temp_price', '>', 0]
        ])->get();

        $ozonProductsList = $this->getProductsListV2();

        $productsToImport = array();
        $foundCount = 0;

        if($all){
            $productsToImport = $productsList;
        }else{
            foreach($productsList as $Product){
                $found = false;
                foreach($ozonProductsList as $ozonProduct)
                {
                    if($Product->sku === $ozonProduct->offer_id)
                    {
                        $found = true;
                        $foundCount++;
                        break;
                    };
                };
                if(!$found){
                    $productsToImport[] = $Product;
                };
            };
        };

        $this->log('info', 'ozonProductsToImport', '
            Количество товаров в Базе: '.count($productsList).PHP_EOL.'
            Количество товаров в Ozon: '.count($ozonProductsList).PHP_EOL.'
            Количество существующих товаров в Ozon: '.$foundCount.PHP_EOL.'
            Количество товаров на импорт в Ozon: '.count($productsToImport).'
        ');

        return $productsToImport;
    }

    public function uploadProducts($productsToImport = false)
    {
        if(!$productsToImport) $productsToImport = $this->ozonProductsToImport();
        $templatesProductsToUpload = $this->productsToTemplates($productsToImport);

        if(count($templatesProductsToUpload) > 0){
            $items = array();
            $i = 0;
            foreach($templatesProductsToUpload as $key => $Product){
                $items[] = $Product;
                $i++;
                // You can send no more than 1000 items
                if(($i == 1000) or (count($templatesProductsToUpload) == ($key + 1))){
                    $i = 0;
                    //$res .= json_decode($this->makeRequest(
                    $req = ['items' => $items];
                    $res = $this->makeRequest(
                        'POST',
                        "/v1/product/import",
                        $req
                    );
                    dump($res);
                    $this->log('info', 'uploadProducts', "POST /v1/product/importUpload - $key", false, $res);
                    $items = array();
                };
            };
        }else{
            $this->log('info', 'uploadProducts', 'Nothing to import');
        };
    }

    public function uploadProductsV2($productsToImport = false, $importStack = 100)
    {
        if(!$productsToImport) $productsToImport = Ozon::getNotAddedProducts($this->shopId); // find not added products
        $countProductsToImport = count($productsToImport);
        dump('Products to Import: ' . $countProductsToImport);

        $templatesProductsToUpload = $this->productsToTemplatesV2($productsToImport); // modify to template for import

        if(count($templatesProductsToUpload) > 0)
        {
            $items = array();
            $i = 0;
            foreach($templatesProductsToUpload as $key => $TemplateProduct)
            {
                dump("$key of $countProductsToImport");
                $items[] = $TemplateProduct;

                $i++;
                // You can send no more than 1000 items
                if(($i === $importStack) or (count($templatesProductsToUpload) === ($key + 1)))
                {
                    $i = 0;
                    $req = ['items' => $items];
                    //dump(json_encode($req));
                    $res = $this->makeRequest(
                        'POST',
                        "/v2/product/import",
                        $req
                    );
                    //dump(json_encode($res));
                    dump("RES");
                    dump($res);
                    $this->log('info', 'uploadProducts2', "POST /v2/product/importUpload - ".($key+1), $req, $res);
                    $items = array();
                };
            };
        }else{
            $this->log('info', 'uploadProducts2', 'Nothing to import');
        };
    }

    public function uploadProductsV3($productsToImport = false, $importStack = 1000)
    {
        if(!$productsToImport) $productsToImport = Ozon::getNotAddedProducts($this->shopId); // find not added products
        dump('Products to Import: ' . count($productsToImport));
        $templatesProductsToUpload = $this->productsToTemplatesV3($productsToImport); // modify to template for import

        if(count($templatesProductsToUpload) > 0)
        {
            $items = array();
            $i = 0;
            foreach($templatesProductsToUpload as $key => $TemplateProduct)
            {
                $items[] = $TemplateProduct;

                $i++;
                // You can send no more than 1000 items
                if(($i === $importStack) or (count($templatesProductsToUpload) === ($key + 1)))
                {
                    $i = 0;
                    $req = ['items' => $items];
                    $res = $this->makeRequest(
                        'POST',
                        "/v2/product/import",
                        $req
                    );
                    var_dump($res);
                    $this->log('info', 'uploadProducts2', "POST /v2/product/importUpload - ".($key+1), $req, $res);
                    $items = array();
                };
            };
        }else{
            $this->log('info', 'uploadProducts2', 'Nothing to import');
        };
    }

    public function productsToTemplates($products, $update = false){
        $templatesProducts = array();
        foreach($products as $key => $Product){
            $TemplateProduct = $this->createTemplateProduct($Product, $update);
            if($TemplateProduct){
                $templatesProducts[] = $TemplateProduct;
            }else{
                $this->log('error', 'productsToTemplates', 'empty template for sku '.$Product->sku);
            };
        };
        return $templatesProducts;
    }

    public function productsToTemplatesV2($products)
    {
        $templatesProducts = array();
        foreach($products as $key => $Product)
        {
            if($TemplateProduct = $this->createTemplateProductV2($Product))
            {
                $templatesProducts[] = $TemplateProduct;
            }else{
                $this->log('error', 'productsToTemplates', 'empty template for sku '.$Product->sku);
            };
        };
        return $templatesProducts;
    }

    public function productsToTemplatesV3($products)
    {
        $templatesProducts = array();
        foreach($products as $key => $Product)
        {
            if($TemplateProduct = $this->createTemplateProductV3($Product))
            {
                $templatesProducts[] = $TemplateProduct;
            }else{
                $this->log('error', 'productsToTemplates', 'empty template for sku '.$Product->sku);
            };
        };
        return $templatesProducts;
    }

    public function getProductInfoV2($sku)
    {
        $req = ['offer_id' => $sku];
        $res = $this->makeRequest(
            'POST',
            '/v2/product/info',
            $req
        );

        if(isset($res->result))
        {
            return $res->result;
        }else
        {
            return false;
        }
    }

    public function getProductsInfo(array $offerIds, $key = 'offer_id')
    {
        $offerIdsArray = array_chunk($offerIds, 200);
        $productsInfos = [];
        foreach($offerIdsArray as $OfferIds)
        {
            $req = [$key => $OfferIds];
            $res = $this->makeRequest(
                'POST',
                "/v2/product/info/list",
                $req
            );

            if(isset($res->result->items))
            {
                $productsInfos = array_merge($productsInfos, $res->result->items);
            }else{
                dump($res);
                $this->log( 'error', 'getProductInfo', 'POST /v2/product/info/list', $req, $res);
            }
        }

        return $productsInfos;
    }

    public function getProductId($sku){
        $body = ['offer_id' => (string) $sku];
        $res = $this->makeRequest(
            'POST',
            "/v1/product/info",
            $body
        );

        if(isset($res->result)){
            return $res->result->id;
        }else{
            $this->log(
                'error',
                'getProductId',
                'POST /v1/product/info ',
                $body,
                $res
            );
            return false;
        }
    }

    public function createTemplateProduct($Product, $update = false)
    {
        $TemplateProduct = new \stdClass();

        if($update){
            $TemplateProduct->product_id = self::getProductId($Product->sku);
        };

        $TemplateProduct->description = strip_tags(trim($Product->temp_short_description), '<br>');
        if(mb_strlen($TemplateProduct->description) === 0) $TemplateProduct->description = 'Описание на данный момент отсутствует.';

        $TemplateProduct->category_id = 17029541; //Кукла
        $TemplateProduct->offer_id = $Product->sku;


        $name = Products::getProductNameForOzon($Product);
        $TemplateProduct->name = $name;
        $TemplateProduct->price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->Warehouse['typeShopId']);

        if($Product->temp_old_price !== 0)
            $TemplateProduct->old_price = Price::recalculatePriceByUnloadingOption($Product->temp_old_price, $this->Warehouse['typeShopId']);

        $TemplateProduct->vat = $this->importVat;

        $TemplateProduct->dimension_unit = 'cm';
        $TemplateProduct->height = $Product->category->temp_height;
        $TemplateProduct->depth = $Product->category->temp_depth;
        $TemplateProduct->width = $Product->category->temp_width;

        $TemplateProduct->weight_unit = 'g';
        $TemplateProduct->weight = $Product->weight;

        $TemplateProduct->vendor = $Product->manufacturer->name;

        if(count($Product->images) == 0){
            $this->log('error', 'createTemplateProduct', 'No images for product '. $Product->sku);
            return false;
        };

        $TemplateProduct->images = array();
        foreach($Product->images as $imageKey => $image)
        {
            if($imageKey === 10) break; // Not more than 10 images
            $TemplateProduct->images[] = array(
                "file_name" => $image->url,
                "default" => (bool) ($imageKey?false:true)
            );
        };

        $badWords = array("Кукла ", "Набор кукол ", ' набор кукол', "(Mattel) для кукол", "(Mattel)", "кукол-", "Набор одежды для ", ' - Игровой набор', ", Игровой набор");
        $model = str_ireplace($badWords, "", Products::getName($Product, $this->shopId));
        $TemplateProduct->attributes[] = array( // Атрибут - [9048] Название модели
            "id" => 9048,
            "value" => $model
        );


        $TemplateProduct->attributes[] = array( // Атрибут - [8229] Тип
            "id" => 8229,
            "value" => (string) ($Product->type->ozon_id??1096) // Default is doll
        );

        return $TemplateProduct;
    }


    public function getCategoriesTree()
    {
        $res = $this->makeRequest(
            'POST',
            "/v2/category/tree"
        );

        if(isset($res->result))
        {
            return $res->result;
        }else
        {
            $this->log('error', 'getCategoriesTree', 'POST /v2/category/tree', NULL, $res);
            dd($res);
        }
    }



    public function  getCategoryAttributes($categoryId)
    {
        $body = [
            'category_id' => [$categoryId], // 17027487
            //'attribute_type' => 'required',
        ];
        $res = $this->makeRequest(
            'POST',
            "/v3/category/attribute",
            $body
        );

        if(isset($res->result[0]->attributes))
        {
            return $res->result[0]->attributes;
        }else
        {
            return [];
        }
    }


    public function saveShopCategoriesAttributeValues($categoryId = false) // this can be CRON
    {
        // ТИП is
        $shopProductsCategories = ShopProductsCategory::where('shop_id', $this->shopId);

        if($categoryId)
        {
            $shopProductsCategories->where('id', $categoryId);
        }else
        {
            $shopProductsCategories->whereIn('id', ProductShopCategory::where('shop_id', $this->shopId)->pluck('shop_products_category_id')->toArray());
        }

        $shopProductsCategories = $shopProductsCategories->get(); // there is need to get categories which already used

        foreach($shopProductsCategories as $key => $ShopProductsCategory)
        {
            dump('Loading category '. $ShopProductsCategory->shop_title . ' ' . ($key + 1) . ' / ' . count($shopProductsCategories));

            $spcas = $ShopProductsCategory->attributes->where('shop_id', $this->shopId)->where('dictionary_id', '!=', 0)->where('id', '!=', 1)->where('stop_loading', 0);

            foreach($spcas as $sKey => $ShopProductsCategoryAttribute)
            {
                dump('Loading category attributes '. $ShopProductsCategoryAttribute->name . ' ' .($sKey + 1) . ' / ' . count($spcas));

                $attributesValues = $this->getShopCategoriesAttributeValues(
                    $ShopProductsCategoryAttribute->shop_attribute_id,
                    $ShopProductsCategory->shop_category_id
                );

                if(count($attributesValues) > 2000) // ONLY LOW QUANTITY ATTRIBUTES!!!
                {
                    dump("more than 1k - $ShopProductsCategoryAttribute->name");
                    continue;
                };

                foreach($attributesValues as $aKey => $AttributeValue)
                {
                    dump('Loading attributes values '.($aKey + 1) . ' / ' . count($attributesValues));

                    ShopProducts::updateShopProductsCategoryAttributeValues(
                        $this->shopId,
                        $ShopProductsCategory->id,
                        $ShopProductsCategoryAttribute->id,
                        $AttributeValue->id,
                        $AttributeValue->value,
                        $AttributeValue->info,
                        $AttributeValue->picture
                    );
                }
            }
        }
    }

    public function getShopCategoriesAttributeValues($attributeId, $categoryId, $lastValueId = false, $countGot = 0): array
    {
        //https://api-seller.ozon.ru/v2/category/attribute/values
        $attributeValues = [];

         $body = [
             'attribute_id' => $attributeId, // 85
             'category_id' => $categoryId, // 17039241
             'limit' => 50,
             //'last_value_id' => 5079944,
         ];
         if($lastValueId) $body['last_value_id'] = $lastValueId;

        $res = $this->makeRequest(
            'POST',
            "/v2/category/attribute/values",
            $body
        );

        if(isset($res->result))
        {
            $countGot = $countGot + count($res->result);
            dump('got '.$countGot);
            $attributeValues = array_merge($attributeValues, $res->result);

            if(isset($res->has_next) and $res->has_next and ($countGot <= 1100)) // temp no more than 1.1k
            {
                $nextList = $this->getShopCategoriesAttributeValues($attributeId, $categoryId, end($res->result)->id, $countGot);
                if($nextList) $attributeValues = array_merge($attributeValues, $nextList);
            }
        }else
        {
            var_dump('no result');
        }

        return $attributeValues;
    }

    public function saveShopCategoriesAttributes($shopCategoryId = false) // this can be CRON
    {
        $shopProductsCategories = ShopProductsCategory::where('shop_id', $this->shopId);

        if($shopCategoryId)
        {
            $shopProductsCategories->where('shop_category_id', $shopCategoryId);
        }else
        {
            $shopProductsCategories->whereIn('id', ProductShopCategory::where('shop_id', $this->shopId)->pluck('shop_products_category_id')->toArray());
        }

        $shopProductsCategories = $shopProductsCategories->get(); // there is need to get categories which already used


        $spcs = count($shopProductsCategories);
        foreach($shopProductsCategories as $key => $ShopProductsCategory)
        {
            dump("(shopProductsCategories) $key / $spcs");
            $categoryAttributes = $this->getCategoryAttributes($ShopProductsCategory->shop_category_id);

            $totalCategoryAttributes = count($categoryAttributes);
            foreach($categoryAttributes as $key2 => $CategoryAttribute)
            {
                dump("(CategoryAttribute) $key2 / $totalCategoryAttributes");

                ShopProducts::updateShopProductsCategoryAttributes(
                    $this->shopId,
                    $ShopProductsCategory->id,
                    $CategoryAttribute->id,
                    $CategoryAttribute->name,
                    $CategoryAttribute->description,
                    $CategoryAttribute->is_required,
                    $CategoryAttribute->dictionary_id
                );
            }
        }

    }

    public function saveShopCategories($shopCategories = false, $shopParentCategoryId = false, $keyCode = false) // this can be CRON
    {
        $isMainThread = false;
        if(!$shopCategories)
        {
            $shopCategories = $this->getCategoriesTree();
            $isMainThread = true;
            $keyCode = uniqid();
        }

        foreach($shopCategories as $ShopCategory)
        {
            ShopProducts::updateShopProductsCategory(
                $this->shopId,
                $ShopCategory->category_id,
                $ShopCategory->title,
                $shopParentCategoryId,
                $keyCode
            );

            if(isset($ShopCategory->children) and !empty($ShopCategory->children))
            {
                $this->saveShopCategories($ShopCategory->children, $ShopCategory->category_id, $keyCode);
            }
        }

        if($isMainThread)
        {
            ShopProductsCategory::where([
                ['shop_id', $this->shopId],
                ['key_code', '!=', $keyCode],
            ])->delete();
        }
    }


    public function createTemplateProductV3($Product)
    {
        if(!($Product->sku === 'P50009')) dd('closed by sku');

        $categoryId = 17029543;

        $ShopProductsCategory = ShopProductsCategory::where('shop_category_id', $categoryId)->first();

        $categoryAttributes = $ShopProductsCategory->attributes??[];
        if(count($categoryAttributes) === 0)
        {
            dump('saveShopCategoriesAttributes');
            $this->saveShopCategoriesAttributes($ShopProductsCategory->shop_category_id); // save category attributes
            $ShopProductsCategory->refresh(); // refresh model
            $categoryAttributes = $ShopProductsCategory->attributes;
            if(count($categoryAttributes) === 0)
            {
                dump('Without category attributes '.$Product->sku);
                return false; // without category attributes
            }
        }

        $TemplateProduct = new \stdClass();
        $TemplateProduct->barcode = $Product->barcode;

        $TemplateProduct->name = Products::getName($Product, $this->shopId);
        $TemplateProduct->category_id = $ShopProductsCategory->shop_category_id;

        $TemplateProduct->offer_id = $Product->sku;

        $TemplateProduct->price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->Warehouse['typeShopId']);

        if($Product->temp_old_price !== 0)
            $TemplateProduct->old_price = Price::recalculatePriceByUnloadingOption($Product->temp_old_price, $this->Warehouse['typeShopId']);

        $TemplateProduct->vat = $this->importVat;
        //$TemplateProduct->dimension_unit = 'cm';
        $TemplateProduct->dimension_unit = 'mm';
        $TemplateProduct->weight_unit = 'g';

        $TemplateProduct->height = ($Product->box_height?:$Product->category->temp_height) * 10;
        $TemplateProduct->depth = ($Product->box_width?:$Product->category->temp_depth)  * 10;
        $TemplateProduct->width = ($Product->box_length?:$Product->category->temp_width)  * 10;

        $TemplateProduct->weight = $Product->weight?:$Product->category->temp_weight;

        if(count($Product->images) === 0)
        {
            $this->log('error', 'createTemplateProduct', 'No images for product '. $Product->sku);
            dump('no images');
            return false;
        };

        $TemplateProduct->images = array();
        foreach($Product->images as $imageKey => $Image)
        {
            if($imageKey === 0) $TemplateProduct->primary_image = $Image->url;
            if($imageKey === 10) break; // Not more than 10 images
            $TemplateProduct->images[] = $Image->url;
        };

        $TemplateProduct->attributes = [];

        foreach($categoryAttributes as $CategoryAttribute)
        {
            $values = [];

            // if product don't have attributes, then set default
            if($DefaultValue = ShopProducts::getDefaultAttributeValue($this->shopId, $Product->id, $CategoryAttribute->id))
            {
                if($DefaultValue->value or $DefaultValue->valueId)
                {
                    $value = new \stdClass();
                    if($DefaultValue->valueId) $value->dictionary_value_id = $DefaultValue->valueId;
                    if(!$DefaultValue->valueId) $value->value = $DefaultValue->value;
                    $values[] = $value;
                }
            }

            if(!empty($values))
            {
                $TemplateProduct->attributes[] = array(
                    'id' => $CategoryAttribute->shop_attribute_id,
                    'values' => $values
                );
            }
        }



        return $TemplateProduct;
    }


    public function createTemplateProductV2($Product)
    {

        $ShopProductsCategory = ShopProductsCategory::where([
            ['shop_category_id', ($Product->category->ozon_id??17029541)],
            ['shop_id', $this->shopId],
        ])->first();

        $categoryAttributes = $ShopProductsCategory->attributes??[];
        if(count($categoryAttributes) === 0)
        {
            dump('saveShopCategoriesAttributes');
            $this->saveShopCategoriesAttributes($ShopProductsCategory->shop_category_id); // save category attributes
            $ShopProductsCategory->refresh(); // refresh model
            $categoryAttributes = $ShopProductsCategory->attributes;
            if(count($categoryAttributes) === 0)
            {
                dump('Without category attributes '.$Product->sku);
                return false; // without category attributes
            }
        }

        $TemplateProduct = new \stdClass();
        $TemplateProduct->barcode = $Product->barcode;

        $TemplateProduct->name = $Product->name_ru;
        $TemplateProduct->category_id = $ShopProductsCategory->shop_category_id;
        $TemplateProduct->offer_id = $Product->sku;

        $TemplateProduct->price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->Warehouse['typeShopId']);

        if($Product->temp_old_price !== 0)
            $TemplateProduct->old_price = Price::recalculatePriceByUnloadingOption($Product->temp_old_price, $this->Warehouse['typeShopId']);

        $TemplateProduct->vat = $this->importVat;

        $TemplateProduct->dimension_unit = 'mm';
        $TemplateProduct->height = (int) $Product->BoxSizes->valueHeight * 10; // updated 2022.04.08
        $TemplateProduct->depth = (int) $Product->BoxSizes->valueLength * 10; // updated 2022.04.08
        $TemplateProduct->width = (int) $Product->BoxSizes->valueWidth * 10; // updated 2022.04.08

        $TemplateProduct->weight_unit = 'g';
        $TemplateProduct->weight = (int) $Product->BoxSizes->valueWeight; // set by Oleg 2022.04.08

        if(count($Product->images) === 0)
        {
            $this->log('error', 'createTemplateProduct', 'No images for product '. $Product->sku);
            dump('no images');
            return false;
        };

        $TemplateProduct->images = array();
        foreach($Product->images as $imageKey => $Image)
        {
            if($imageKey === 0) $TemplateProduct->primary_image = $Image->url;
            if($imageKey === 10) break; // Not more than 10 images
            $TemplateProduct->images[] = $Image->url;
        };

        $TemplateProduct->attributes = [];

        foreach($categoryAttributes as $CategoryAttribute)
        {
            $values = [];

            // if product don't have attributes, then set default
            if($DefaultValue = ShopProducts::getDefaultAttributeValue($this->shopId, $Product->id, $CategoryAttribute->id))
            {
                if($DefaultValue->value or $DefaultValue->valueId)
                {
                    $value = new \stdClass();
                    if($DefaultValue->valueId) $value->dictionary_value_id = $DefaultValue->valueId;
                    if(!$DefaultValue->valueId) $value->value = $DefaultValue->value;
                    $values[] = $value;
                }
            }

            if(!empty($values))
            {
                $TemplateProduct->attributes[] = array(
                    'id' => $CategoryAttribute->shop_attribute_id,
                    'values' => $values
                );
            }
        }

        return $TemplateProduct;
    }

    public function removeProducts($productsToRemove){
        foreach($productsToRemove as $productToRemove){
            $req = array(
                'product_id' => $productToRemove->product_id,
                'offer_id' => $productToRemove->offer_id
            );
            $res = $this->makeRequest(
                'POST',
                '/v1/product/delete',
                $req
            );

            if(isset($res->result)){
                if($res->result->deleted){
                    $this->log('warning', 'removeProducts', 'Deleted '.$productToRemove->offer_id);
                    continue;
                };
            };
            $this->log('error', 'removeProducts', 'POST /v1/product/delete', $req, $res);
        };
    }

    public function activateProducts($productsToActivate){
        foreach($productsToActivate as $ProductToActivate){
            $body = ['product_id' => $ProductToActivate->product_id];
            $res = $this->makeRequest(
                'POST',
                '/v1/product/activate',
                $body
            );

            if(isset($res->result)){
                $this->log('warning', 'activateProducts', 'Activated '.$ProductToActivate->offer_id);
                continue;
            };
            $this->log('error', 'activateProducts', 'POST /v1/product/activate', $body, $res);
        };
    }

    public function deactivateProducts($productsToDeactivate){
        foreach($productsToDeactivate as $ProductToDeactivate){
            $req = ['product_id' => $ProductToDeactivate->product_id];
            $res = $this->makeRequest(
                'POST',
                '/v1/product/deactivate',
                $req
            );

            if(isset($res->result)){
                $this->log('warning', 'deactivateProducts', 'Deactivate '.$ProductToDeactivate->offer_id, $req, $res);
                continue;
            };
            $this->log('error', 'deactivateProducts', 'POST /v1/product/deactivate', $req, $res);
        };
    }

    function getTransactionReport($dateFrom, $dateTo, $transactionType, $search = false)
    {
        $req = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,

            'transaction_type' => $transactionType
        ];
        if($search) $req['search'] = $search;

        $res = $this->makeRequest(
            'POST',
            '/v1/report/transactions/create',
            $req
        );
        if(isset($res->result->code)){
            $this->log('info', 'getTransactionReport', 'ok', $req, $res);
            return $res->result->code;
        }else{
            $this->log('error', 'getTransactionReport', 'error request', $req, $res);
            return false;
        }
    }

    function getReportInfo($code, $waitResult = false)
    {
        $req = [
            'code' => $code
        ];
        $res = $this->makeRequest(
            'POST',
            '/v1/report/info',
            $req
        );

        if($waitResult) {
            if(isset($res->result->status)){
                if(in_array($res->result->status, ['waiting', 'processing'])){
                    echo($res->result->status.PHP_EOL);
                    $retryAfter = 2;
                    while($retryAfter > 0){
                        echo "getReportInfo - Waiting for $retryAfter sec \r";
                        Sleep(1);
                        $retryAfter--;
                    };
                    return self::getReportInfo($code, $waitResult);
                };
            }else{
                $this->log('error', 'getReportInfo', 'can not get res->status', $req, $res);
                return false;
            };
        };

        if($res->result->status === 'success'){
            return $res->result;
        }else{
            $this->log('error', 'getReportInfo', 'status is not success', $req, $res);
        }
    }

    public function getPriceV4($offer_id, $getNewFromAPI = true)
    {
        $prices = $this->getPricesV4(['offer_id' => [$offer_id]], $getNewFromAPI);

        foreach($prices as $price)
        {
            if($price->offer_id === $offer_id)
            {
                return $price;
            }
        }

        return false;
    }
    public function getPricesV4($filter = false, $getNewFromAPI = true)
    {
        $limit = 1000;
        $lastId = false;

        if(!$filter or !$getNewFromAPI)
        {
            $filter =  [
                'visibility' => 'ALL'
            ];
        }

        if(empty($this->productsPricesV4) or $getNewFromAPI)
        {
            $stopper = false;
            $i = 0;
            while(!$stopper)
            {
                $req = [
                    'filter' => $filter,
                    'limit' => $limit
                ];

                if($lastId) $req['last_id'] = $lastId;

                $res = $this->makeRequest(
                    'POST',
                    "/v4/product/info/prices",
                    $req
                );

                if(isset($res->result->items))
                {
                    if(count($res->result->items) > 0){
                        $this->productsPricesV4 = array_merge($this->productsPricesV4, $res->result->items);
                        $lastId = $res->result->last_id;
                    }else{
                        $stopper = true;
                    };
                    if(count($res->result->items) < 1000) $stopper = true;

                    $i++;
                    if($i === 10){
                        $stopper = true;
                        $this->log('error', 'getPricesV4', 'Ozon getPricesV4 stopper!', $req, $res);
                        die('Ozon getPrices stopper!');
                    };
                }else{
                    $stopper = true;
                    $this->log('error', 'getPricesV4', 'POST /v4/product/info/prices', $req, $res);
                }
            };
        };

        return $this->productsPricesV4;
    }

    public function getStoreProducts()
    {
        if(empty($this->storeProductsList))
        {
            switch($this->Warehouse['id'])
            {
                case 1: // STV
                    $this->storeProductsList = $this->getInsalesProducts();
                    break;
                case 2: // MSK
                    $this->storeProductsList = $this->getRitmzProducts();
                    break;
                default:
                    $this->log('error', 'getStoreProducts', 'Undefended warehouse id');
                    die();
            }
        }

        return $this->storeProductsList;
    }



    public function getStoreStock($Product)
    {
        if($Product)
        {
            return $Product->shopAmounts($this->shopId)->amounts->balance;
        }else{
            return false; // do not found product
        }
    }

    public function getRitmzProducts()
    {
        if(empty($this->ritmzProducts)) $this->ritmzProducts = (new RitmzApi())->getRemains();
        return $this->ritmzProducts;
    }

    public function getInsalesProducts()
    {
        if(empty($this->insalesProducts)) $this->insalesProducts = (new InsalesApi())->getProducts();
        return $this->insalesProducts;
    }

    public function getStorePrice($Product)
    {
        if($Product)
        {
            $price = new \stdClass();
            $price->price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->Warehouse['typeShopId']);
            $price->old_price = Price::recalculatePriceByUnloadingOption($Product->temp_old_price, $this->Warehouse['typeShopId']);
            return $price;
        }

        return false;
    }

    public function importPrices($pricesToImport)
    {
        $this->log('info', 'importPrices', 'To import price: '. count($pricesToImport) .' products');

        $countImport = $countImportStacks = 0;

        if(count($pricesToImport) > 0)
        {

            $arrayOfPrices = array_chunk($pricesToImport, 500);
            foreach($arrayOfPrices as $key => $partPricesToImport)
            {
                $res = $this->makeRequest(
                    'POST',
                    '/v1/product/import/prices',
                    [
                        'prices' => $partPricesToImport
                    ]
                );
                $countImport += count($partPricesToImport);
                $countImportStacks++;

                dump('/v1/product/import/prices', ($key+1));

                if(isset($res->result) and (count($res->result) > 0))
                {
                    $this->log('info', 'importPrices', 'POST /v1/product/import/prices', $partPricesToImport, $res);

                    foreach($res->result as $ResPrice)
                    {
                        if(!$ResPrice->updated)
                            $errors[] = $ResPrice;
                    };
                }else{
                    $this->log('error', 'importPrices', 'POST /v1/product/import/prices', $partPricesToImport, $res);
                };
            }
        }else{
            $this->log('error', 'importPrices', 'not found prices');
        };

        $this->log('info', 'importPrices', 'updated '.$countImport.' product prices and is '.$countImportStacks.' stacks');

        if(isset($errors)){
            $this->log('error', 'importPrices','Found errors', false, $errors);
        };
    }

    public function filterBeforeImportByIndex(&$stocksToImport, &$TypeShopId, $log)
    {
        $maxPriceAvgIndex = Ozon::getMaxIndex($TypeShopId)->maxIndex;

        if($log) echo PHP_EOL."maxPriceAvgIndex $maxPriceAvgIndex";

        $nowPriceAvgIndex = 0;
        $nowPriceAvgIndexCount = 0;
        usort($stocksToImport, function ($a, $b){
            $aValue = empty($a->price_index)?0:$a->price_index;
            $bValue = empty($b->price_index)?0:$b->price_index;
            return $aValue > $bValue;
        });

        foreach($stocksToImport as $StockToImport)
        {
            if(!empty($StockToImport->price_index) and ($StockToImport->stock > 0))
            {
                $nowPriceAvgIndex += $StockToImport->price_index;
                $nowPriceAvgIndexCount ++;
                //$nowPriceIndex = round($nowPriceAvgIndex/$nowPriceAvgIndexCount, 2);
                $nowPriceIndex = $nowPriceAvgIndex/$nowPriceAvgIndexCount;

                if(!($nowPriceIndex  < $maxPriceAvgIndex))
                {
                    //this stocks need to set to 0 and set systems_products_stops->stop_stock = 1 and stop_stock_until = 2022-
                    $StockToImport->stock = 0;
                    try{
                        $Product = Products::getProductBy('sku', $StockToImport->offer_id);
                        if($Product) Products::setSystemsProductStop($Product, $TypeShopId, 'Ограничитель 1');
                    }catch(\Exception $e){
                        $this->log('error', 'filterBeforeImportByIndex', $e);
                    }
                }
            }
        }
    }

    public function importStocks($stocksToImport, $totalCount, $log)
    {
        // filter more then needle index
        //removed from 2022-12-02
        //$this->filterBeforeImportByIndex($stocksToImport, $this->Warehouse['typeShopId'], $log);

        if($log)
        {
            if($log === 'return_stocksToImport')
            {
                return $stocksToImport;
            }
        }

        $this->log('info', 'importStocks', 'To update left overs: '. count($stocksToImport).'/'.$totalCount);

        $countImport = $countImportStacks = 0;

        if(count($stocksToImport) > 0)
        {
            $productsStockUpdate = new \stdClass();
            $productsStockUpdate->stocks = array();
            $i = 0;

            foreach($stocksToImport as $key => $Stock)
            {
                $productsStockUpdate->stocks[] = $Stock;
                $i++;
                if(($i == 100) or (count($stocksToImport) == ($key + 1))) // Can send no more than 100 elements
                {

                    $res = $this->updateStocks($productsStockUpdate->stocks);

                    $countImport += $i;
                    $countImportStacks++;

                    if(isset($res->result) and (count($res->result) > 0))
                    {
                        $this->log('info', 'importStocks', 'POST /v1/product/import/stocks', $productsStockUpdate, $res);

                        foreach($res->result as $ResStock)
                        {
                            if(!$ResStock->updated)
                                $errors[] = $ResStock;
                        };
                    }else{
                        $this->log('error', 'importStocks', 'POST /v1/product/import/stocks', $productsStockUpdate, $res);
                    };

                    $i = 0; // Обнуляем счётчик товаров
                    $productsStockUpdate->stocks = array(); // Очищаем массив цен для нового стека
                };
            };
        }else{
            $this->log('error', 'importStocks', 'Do not found left overs');
        };

        $this->log('info', 'importStocks', 'Updated '.$countImport.' product left overs and is '.$countImportStacks.' stacks');

        if(isset($errors)){
            $this->log('error', 'importStocks','Found errors', false, $errors);
        }else{
            $this->log('info', 'importStocks', 'Errors not found');
        };

        return true;
    }

    public function updateOrderTransactions($Order, $returnCount = false)
    {
        $i=0;
        $i2=0;
        $transactions = $this->getOrderTransactions($Order);
        if($transactions)
        {
            SystemsTransaction::where([
                ['order_id', '=', $Order->id],
                ['type_shop_id', '=', $Order->type_shop_id]
            ])->delete();

            foreach($transactions as $Transaction)
            {
                if($this->transactionCreate($Transaction)){
                    $i++;
                }else{
                    $i2++;
                };
            }
        }

        if($returnCount)
        {
            return $i;
        }else{
            return "Создано/обновлено $i из ".count($transactions)." транзакций. Ошибок: $i2";
        }
    }

    public function getOrderTransactions($Order)
    {
        $dateFrom = Carbon::parse($Order->info->order_date_create)->setTimezone('Europe/Moscow')->startOfDay()->format('Y-m-d\TH:i:s.000\Z');
        $dateTo = Carbon::now()->setTimezone('Europe/Moscow')->format('Y-m-d\TH:i:s.000\Z');
        return $this->getTransactions($dateFrom, $dateTo, $Order->system_order_id);
    }

    public function getTransactions($dateFrom = false, $dateTo = false, $SystemOrderId = false, string $transactionType = NULL)
    {
        $transactions = [];
        $stopper = false;
        $i = 0;
        $pageNow = 1;
        $filter = ['transaction_type' => $transactionType??'all'];
        if($dateFrom)
            $filter['date']['from'] = $dateFrom;
        if($dateTo)
            $filter['date']['to'] = $dateTo;
        if($SystemOrderId)
            $filter['posting_number'] = $SystemOrderId;

        $req = [
            'filter' => $filter,
            'page_size' => 1000
        ];

        while (!$stopper)
        {
            $req['page'] = $pageNow;

            $res = $this->makeRequest(
                'POST',
                "/v2/finance/transaction/list",
                $req
            );

            if (isset($res->result))
            {
                if (count($res->result) > 0)
                {
                    $pageNow++;
                    $transactions = array_merge($transactions, $res->result);
                }else{
                    $stopper = true;
                };

                $i++;
                if($i === 100)
                {
                    //$stopper = true;
                    $this->log('error', 'getTransactions', 'Ozon getTransactions stopper!', $req, $res);
                    die('Ozon getTransactions stopper!');
                };
            }else{
                $stopper = true;
                die('Ozon getTransactions stopper!');
                $this->log('error', 'getTransactions', 'POST /v2/finance/transaction/list', $req, $res);
            }
        };

        return $transactions;
    }

    public function transactionCreate($Transaction)
    {
        //print_r($Transaction->transactionTypeSlug);
        $SystemsTransactionsType = SystemsTransactionsType::where('alias', $Transaction->transactionTypeSlug)->first();
        if(!$SystemsTransactionsType)
        {
            $this->log('error', 'saveTransactions', 'Unknown transaction type '.$Transaction->transactionTypeSlug);
            die('Error transaction type');
        }

        $SystemsTransaction =
            SystemsTransaction::where([
                ['type_shop_id', $this->Warehouse['typeShopId']],
                ['system_transaction_number', $Transaction->transactionNumber],
                ['transaction_type_id', $SystemsTransactionsType->id],
            ])->first();
        if(!$SystemsTransaction) $SystemsTransaction = new SystemsTransaction;

        $SystemsTransaction->type_shop_id = $this->Warehouse['typeShopId'];
        $SystemsTransaction->order_system_number = $Transaction->orderNumber;
        $Order = Orders::getOrder($Transaction->orderNumber, $this->systemId);
        if($Order)
        {
            $SystemsTransaction->order_id = $Order->id;

            if ($Order->sale)
                $SystemsTransaction->sale_id = $Order->sale->id;
        }

        $SystemsTransaction->transaction_type_id = $SystemsTransactionsType->id;
        $SystemsTransaction->total_amount = $Transaction->totalAmount;
        $SystemsTransaction->transaction_date = Carbon::parse($Transaction->tranDate, 'Europe/Moscow')->setTimezone('UTC');
        $SystemsTransaction->system_transaction_number = $Transaction->transactionNumber;
        $SystemsTransaction->details = $Transaction->details;

        $SystemsTransaction->order_amount = $Transaction->orderAmount;
        $SystemsTransaction->discount_amount = $Transaction->discountAmount;
        $SystemsTransaction->commission_amount = $Transaction->commissionAmount;
        $SystemsTransaction->item_delivery_amount = $Transaction->itemDeliveryAmount;
        $SystemsTransaction->item_return_amount = $Transaction->itemReturnAmount;

        if($SystemsTransaction->save())
        {
            return true;
        }else{
            return false;
        }
    }

    public function saveTransactions($dateFrom = false)
    {
        if(!$dateFrom)
        {
            $LastTransaction = SystemsTransaction::where('type_shop_id', $this->Warehouse['typeShopId'])->orderBy('transaction_date', 'desc')->first();
            if($LastTransaction)
            {
                $dateFrom = Carbon::parse($LastTransaction->transaction_date, 'UTC')->setTimezone('Europe/Moscow')->startOfDay()->format('Y-m-d\TH:i:s.000\Z');
            }else{
                $dateFrom = Carbon::parse('2020-03-24', 'Europe/Moscow')->startOfDay()->format('Y-m-d\TH:i:s.000\Z');
            }
        }else{
            $dateFrom = Carbon::parse($dateFrom, 'Europe/Moscow')->startOfDay()->format('Y-m-d\TH:i:s.000\Z');
        }

        $dateTo = Carbon::now()->setTimezone('Europe/Moscow')->format('Y-m-d\TH:i:s.000\Z');

        $transactions = $this->getTransactions($dateFrom, $dateTo);

        foreach($transactions as $Transaction)
        {
            $this->transactionCreate($Transaction);
        }

        $this->log('info', 'saveTransactions', 'Get '.count($transactions).' transactions');
    }

    public function getProductIndex($sku)
    {
        $price_index = 0;
        $prices = $this->getPricesV4();
        foreach($prices as $price)
        {
            if($price->offer_id === $sku)
            {
                $price_index = (float) $price->price_index;
                break;
            }

        }
        return $price_index;
    }

    public function getIndex($showProducts = false)
    {
        $visibleOzonProducts = $this->getProductListV2(true);

        $count = 0;
        $allPrice = 0;
        if($showProducts) $products = [];

        foreach ($visibleOzonProducts as $VisibleOzonProduct)
        {
            if(($VisibleOzonProduct->price_index !== '0.00'))
            {
                $totalCount = 0;
                if(isset($VisibleOzonProduct->stocks->present)) $totalCount = $VisibleOzonProduct->stocks->present;
                if(isset($VisibleOzonProduct->stocks->reserved)) $totalCount -= $VisibleOzonProduct->stocks->reserved;
                if($totalCount > 0)
                {
                    $count++;
                    $allPrice += (float) $VisibleOzonProduct->price_index;
                    $products[] = $VisibleOzonProduct;
                }
            }
        }

        $res = ['totalCount' => $count, 'totalPriceIndex' => $allPrice/$count];
        if($showProducts) $res['products'] = $products;

        return (object) $res;
    }

    public function saveIndex()
    {
        $Index = $this->getIndex();
        if($Index)
        {
            $OzonIndexPrice = new OzonIndexPrice;
            $OzonIndexPrice->price_index = (float) $Index->totalPriceIndex;
            $OzonIndexPrice->count_products = $Index->totalCount;
            $OzonIndexPrice->type_shop_id = $this->Warehouse['typeShopId'];
            $OzonIndexPrice->count_hidden_products = SystemsProductsStop::where([
                ['orders_type_shop_id', '=', $this->Warehouse['typeShopId']],
                ['stop_stock', '=', 1]
            ])->whereNotNull('stop_stock_until')->count();
            $OzonIndexPrice->count_stop_price_products = SystemsProductsStop::where([
                ['orders_type_shop_id', '=', $this->Warehouse['typeShopId']],
                ['stop_price', '=', 1]
            ])->count();
            $OzonIndexPrice->count_stop_stock_products = SystemsProductsStop::where([
                ['orders_type_shop_id', '=', $this->Warehouse['typeShopId']],
                ['stop_stock', '=', 1]
            ])->count();
            $OzonIndexPrice->save();
        }
    }


    public function filterBeforeImportByForceStock(&$indexes, $typeShopId, $log)
    {
        if($log){
            print_r("count indexes before ".count($indexes).PHP_EOL);
        }

        $maxAvgIndex = Ozon::getMaxIndex($typeShopId)->maxIndex;

        usort($indexes, function ($a, $b)
        {
            $aForced = empty($a->force_stock)?false:true;
            $bForced = empty($b->force_stock)?false:true;

            if(($aForced and $bForced) or (!$aForced and !$bForced))
            {
                $aValue = empty($a->price_index)?0:$a->price_index;
                $bValue = empty($b->price_index)?0:$b->price_index;
                return $aValue > $bValue;
            }else{
                return $aForced?false:true;
            }
        });

        $noStocksAndIndexes = [];
        $forceIndexes = [];
        $forceIndexesSum = 0;
        foreach($indexes as $key => $Index)
        {
            if(($Index->stock === 0) or empty($Index->price_index) or !$Index->price_index)
            {
                //if($log and ($key > 500)) dd($Index);
                $noStocksAndIndexes[] = $Index;
                unset($indexes[$key]);
            }elseif(!empty($Index->force_stock))
            {
                $forceIndexes[] = $Index;
                $forceIndexesSum += $Index->price_index;
                unset($indexes[$key]);
            };
        }

        $indexes = array_values($indexes);

        $forcedCount = count($forceIndexes);
        $sumIndex = $forceIndexesSum;
        $countIndex = $forcedCount;
        $compIndexes = $forceIndexes;

        foreach($indexes as $key => $Index)
        {
            $nowAvgIndex = ($sumIndex + $Index->price_index)/($countIndex + 1);
            if(($nowAvgIndex < $maxAvgIndex) or ($Index->price_index < $maxAvgIndex)) // if index exceeds value
            {
                $sumIndex += $Index->price_index;
                $countIndex ++;
                $compIndexes[] = $Index; // index ok
                unset($indexes[$key]);
            }
        }

        $indexes = array_values($indexes); // only < MaxAvgIndex

        while(($countIndex > 0) and (!(round($sumIndex/$countIndex, 2) < $maxAvgIndex)) and ($forcedCount > 0)) // to big index with forced - try to dismiss something
        {
            $FirstForce = array_shift($compIndexes);
            $sumIndex -= $FirstForce->price_index;

            $countIndex --;
            $forcedCount --;
        }

        if(
            (($countIndex === 0) // if nothing added
                or
            ((round($sumIndex/$countIndex, 2) < $maxAvgIndex)) and (count($indexes) > 0)) // if can insert any
        )
        {
            foreach($indexes as $key => $Index)
            {
                $nowAvgIndex = ($sumIndex + $Index->price_index)/($countIndex + 1);
                if(($nowAvgIndex < $maxAvgIndex))
                {
                    if($log) dump($nowAvgIndex,'<', $maxAvgIndex);
                    $sumIndex += $Index->price_index;
                    $countIndex ++;
                    $compIndexes[] = $Index;
                    unset($indexes[$key]);
                }
            }
        }

        $indexes = array_values($indexes); // this indexes need set to zero

        // need set indexes Zero and StopIndexes, because its has big index
        foreach($indexes as $Index)
        {
            $Product = Products::getProductBy('sku', $Index->offer_id);
            if($Product) Products::setSystemsProductStop($Product, $this->Warehouse['typeShopId'], 'Ограничитель 3 (фильтр FORCE)');
            $Index->stock = 0;
        }

        $indexes = array_merge($noStocksAndIndexes, $compIndexes);

        if($log)
        {
            print_r("count indexes after ".count($indexes).PHP_EOL);
            print_r("sumIndex: ". ($sumIndex/$countIndex).PHP_EOL);
        }
    }


    public function getProductListV2($visible, $test = false)
    {
        $productList = $this->getProductsListV2();
        $productsIds = [];
        foreach($productList as $OzonProduct)
        {
            $productsIds[] = $OzonProduct->offer_id;
        }
        $productInfos = $this->getProductsInfo($productsIds);

        foreach($productInfos as $key => $OzonProductInfo)
        {
            $dismiss = $visible?(!$OzonProductInfo->visible):($OzonProductInfo->visible);
            if($dismiss) unset($productInfos[$key]);
        }

        return array_values($productInfos);
    }




    public function updatePricesAndStocks($log = false)
    {
        $start = microtime(true);
        $visibleOzonProducts = $this->getProductListV2(true);
        $visibleOzonProductsCount = count($visibleOzonProducts);

        $stocksToImport = [];
        $pricesToImport = [];
        $countForcedStock = 0;

        if($log) print_r("total products $visibleOzonProductsCount".PHP_EOL);

        foreach($visibleOzonProducts as $key => $VisibleOzonProduct)
        {
            if($log) print_r($key.' of '.$visibleOzonProductsCount."\r");

            $sku = $VisibleOzonProduct->offer_id;
            $Product = Products::getProductBy('sku', $sku);


            $stock = new \stdClass();
            if($Product)
            {
                $OzonStock = $VisibleOzonProduct->stocks;

                // get SystemsProductsStops
                $Stop = Products::getSystemsProductsStopResult($Product, $this->shopId);

                $StoreStock = $this->getStoreStock($Product);
                if($Stop->stock and !$Stop->force_stock)
                {
                    $StoreStock = 0;
                }else{
                    if($StoreStock === false)
                    {
                        $StoreStock = 0;
                    }
                }

                if($Stop->force_stock and ($StoreStock > 0))
                {
                    $stock->force_stock = true;
                    $countForcedStock++;
                }

                $StorePrice = $this->getStorePrice($Product); // need set: false = not found, 0 = 0
                if($Stop->price)
                {
                    $StorePrice = false;
                }else{
                    if($StorePrice === false) // if price not found
                    {
                        $StoreStock = 0;
                    }
                }
            }else{
                $StoreStock = 0;
                $StorePrice = $OzonStock = false;

                $this->log('error', 'updatePricesAndStocks', 'Undefended product '.$sku);
            }

            if($StorePrice)
            {
                $price = new \stdClass();
                $price->offer_id = $VisibleOzonProduct->offer_id;
                $price->price = $StorePrice->price;

                /* Removed from 2022-12-02
                if(in_array($this->shopId, [2, 10001])) // now only for OzonMSK, OzonSTV2
                {
                    if($minPrice = Ozon::getOzonMinPrice($price->offer_id, $this))
                    {
                        $checkStorePrice = (float) $price->price;
                        if($minPrice <= $checkStorePrice)
                        {
                            $price->min_price = (string) $minPrice;
                        }else
                        {
                            if($minPrice = Ozon::getOzonMinPrice($price->offer_id, $this, $checkStorePrice))
                            {
                                $price->min_price = (string) $minPrice;
                            }else
                            {
                                dump("no min price 2 $price->offer_id");
                            }
                        }
                    }else
                    {
                        dump("no min price $price->offer_id");
                    }
                }
                */

                if($premiumPrice = $this->getPremiumPrice($price->price))
                    $price->premium_price = $premiumPrice;


                if(((int) $StorePrice->old_price > 0) and ($StorePrice->old_price !== $StorePrice->price))
                {
                    $price->old_price = $StorePrice->old_price;
                }else{
                    $price->old_price = '0';
                }

                $pricesToImport[] = $price;
            }


            $stock->offer_id = $VisibleOzonProduct->offer_id;
            if($StoreStock > 0) // if store stock > 0 then get Index for
            {
                $stock->price_index = (float) $VisibleOzonProduct->price_index;
            }

            if($OzonStock)
            {
                if($OzonStock->reserved > 0) $StoreStock = $StoreStock - $OzonStock->reserved;
                if($StoreStock < 0) $StoreStock = 0;
            }

            $stock->stock = $StoreStock;

            /* TEMP STOCK REMOVE
            if($this->Warehouse['typeShopId'] === 2)
            {
                $stock->stock = 0; // temp stock
            }else{
                $stock->stock = $StoreStock;
            }
            */

            $stocksToImport[] = $stock;
        }

        if(!empty($pricesToImport)) $this->importPrices($pricesToImport);

        if($countForcedStock > 0)
        {
            // removed from 2022-12-02
            //$this->filterBeforeImportByForceStock($stocksToImport, $this->Warehouse['typeShopId'], $log);
        }


        $res = $this->importStocks($stocksToImport, $visibleOzonProductsCount, $log);

        dump('Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек.');
        $this->log('info', 'updatePricesAndStocks', 'Script execution time '.round(microtime(true) - $start, 4).'sec');

        return $res?:true;
    }

    public function getPremiumPrice($price)
    {
        if(is_string($price)) $price = (float) $price;

        if($price > 0)
        {
            $premiumPrice = false;
            $OzonPremiumPrice = OzonPremiumPrice::where(function($q) use ($price)
            {
                $q->where([['price_from', '<', $price]])
                    ->orWhereNull('price_from');
            })->where(function($q) use ($price)
            {
                $q->where([['price_to', '>=', $price]])
                    ->orWhereNull('price_to');
            })->first();
            if($OzonPremiumPrice and ($OzonPremiumPrice->discount_value or $OzonPremiumPrice->discount_percent))
            {
                $premiumPrice = $OzonPremiumPrice->discount_value?($price - $OzonPremiumPrice->discount_value):($price - $price * $OzonPremiumPrice->discount_percent / 100);
            }
            return (string) floor($premiumPrice);
        }else{
            return false;
        }
    }

    public function getInvisibleProductsInfo()
    {
        $invisibleProducts = $this->getProductsListV2(['visibility' => 'INVISIBLE']);
        $failedProducts = false;

        $productsInfo = [];
        $productsIds = [];

        if($invisibleProducts)
        {
            foreach($invisibleProducts as $InvisibleProduct)
            {
                $productsIds[] = $InvisibleProduct->offer_id;
            }
        }

        if($failedProducts)
        {
            foreach($failedProducts as $FailedProduct)
            {
                $productsIds[] = $FailedProduct->offer_id;
            }
        }

        if(!empty($productsIds))
        {
            $productsInfo = $this->getProductsInfo($productsIds);
        }

        return $productsInfo;
    }

    public function removeStopStockWhereIndexZero()
    {
        $stopStockRemovedCount = 0;
        $stopStocks = SystemsProductsStop::where([
            ['stop_stock', 1],
            ['orders_type_shop_id', $this->shopId]
        ])->where(function($q){
            $q->whereBetween('stop_stock_until', ['2021-01-01', '2021-01-02'])
                ->orWhereBetween('stop_stock_until', ['2022-01-01', '2022-01-02'])
                    ->orWhereBetween('stop_stock_until', ['2023-01-01', '2023-01-02'])
                        ->orWhereBetween('stop_stock_until', ['2024-01-01', '2024-01-02']);
        })->get();

        foreach($stopStocks as $StopStock)
        {
            $sku = $StopStock->product->sku;
            if($this->getProductIndex($sku) == 0)
            {
                $StopStock->stop_stock = 0;
                $StopStock->user_id = 0;
                $StopStock->save();
                $stopStockRemovedCount++;
                $this->log('warning', 'removeStopStockWhereIndexZero', "$sku set stop-stock = 0 : ozon-index = 0");
            }
        }

        if($stopStockRemovedCount > 0)
            $this->log('info', 'removeStopStockWhereIndexZero', "stop-stock removed count = $stopStockRemovedCount");
    }

    public function getStoreProductSku($StoreProduct)
    {
        switch($this->Warehouse['id'])
        {
            case 1: $storeSku = $StoreProduct->variants[0]->sku; break; // STV
            case 2: $storeSku = $StoreProduct->item->id; break; // MSK
            default: $this->log('error', 'getStoreProductSku', 'Undefended warehouse id'); die();
        }

        return $storeSku;
    }

    public function productsUnarchive()
    {
        $offerIds = [];
        $storeProductsList = $this->getStoreProducts();
        foreach($storeProductsList as $StoreProduct)
        {
            $storeSku = $this->getStoreProductSku($StoreProduct);
            $Product = Products::getProductBy('sku', $storeSku);

            if($Product)
            {
                $Stop = Products::getSystemsProductsStopResult($Product, $this->shopId);
                $StoreStock = $this->getStoreStock($Product);

                if(($Stop->stock and !$Stop->force_stock) or ($StoreStock === false)) $StoreStock = 0;
                if($StoreStock) $offerIds[] = $Product->sku;
            }
        }

        $ozonProductsInfos = $this->getProductsInfo($offerIds);
        $ozonProductsIds = [];
        foreach($ozonProductsInfos as $OzonProductsInfo)
        {
            $ozonProductsIds[] = $OzonProductsInfo->id;
        }

        if(count($ozonProductsIds) > 0)
        {
            $i = 0;
            foreach($ozonProductsIds as $key => $ozonProductId)
            {
                $partOfOzonProductsIds[] = $ozonProductId;
                $i++;

                if(($i === 100) or (count($ozonProductsIds) === ($key + 1)))
                {
                    $req = ['product_id' => $partOfOzonProductsIds];
                    $res = $this->makeRequest(
                        'POST',
                        "/v1/product/unarchive",
                        $req
                    );

                    $this->log(
                        isset($res->error)?'error':'info',
                        'productsUnarchive',
                        "POST /v1/product/unarchive - $i",
                        $req,
                        $res
                    );
                    $partOfOzonProductsIds = [];
                    $i = 0;
                };
            };
        }else{
            $this->log('info', 'productsUnarchive', 'Nothing to unarchive');
        };
    }

    public function downwardIndex() // Clearing the highest indexes from Ozon after upload stocks
    {
        $prices = $this->getPricesV4();

        $count = 0;
        $allPrice = 0;
        $pricesWithIndex = [];
        foreach ($prices as $price)
        {
            if(($price->price_index !== '0.00'))
            {
                $Product = Products::getProductBy('sku', $price->offer_id);
                if($Product)
                {
                    $stock = $this->getStockV3($Product->sku);
                }else
                {
                    continue;
                }

                if($stock and ($stock->stock->present > 0))
                {
                    $count++;
                    $allPrice += (float)$price->price_index;
                    $price->product = $Product;
                    $pricesWithIndex[] = $price;
                }

            }
        }

        usort($pricesWithIndex, function ($a, $b)
        {
            $aValue = (float)$a->price_index;
            $bValue = (float)$b->price_index;
            return $aValue < $bValue;
        });

        $maxAvgIndex = Ozon::getMaxIndex($this->shopId)->maxIndex;
        $arrayForStopStock = [];
        foreach($pricesWithIndex as $PriceWithIndex)
        {
            if($count > 0)
            {
                if(!(round($allPrice / $count, 2) < $maxAvgIndex))
                {
                    $Stop = Products::getSystemsProductsStopResult($PriceWithIndex->product, $this->shopId);
                    $OzonStock = $this->getStockV3($PriceWithIndex->product->sku);

                    if(!$Stop->force_stock and ($OzonStock->stock->present !== $OzonStock->stock->reserved)) // if not forced
                    {
                        $arrayForStopStock[] = $PriceWithIndex;

                        $count--;
                        $allPrice = $allPrice - (float) $PriceWithIndex->price_index;
                    }
                }else{
                    break;
                }
            }else{
                return false;
            }
        }

        $stocksToImport = [];
        if (round($allPrice / $count, 2) < $maxAvgIndex)
        {
            foreach($arrayForStopStock as $ForStopStock)
            {
                Products::setSystemsProductStop($ForStopStock->product, $this->shopId, 'Ограничитель 2');

                $stock = new \stdClass();
                $stock->offer_id = $ForStopStock->offer_id;
                $stock->stock = 0;
                $stocksToImport[] = $stock;
            }
        }

        if(count($stocksToImport) > 0)
        {
            $errors = [];

            $this->updateStocks($stocksToImport);

            /*
            if($this->shopId === 1)
            {
                $res = $this->productImportStocksV1($stocksToImport);
            }else{
                $this->updateStocks($stocksToImport);
            }
            */

            if(isset($res->result) and (count($res->result) > 0))
            {
                $this->log('info', 'downwardIndex', 'POST /v1/product/import/stocks', $stocksToImport, $res);

                foreach($res->result as $ResStock)
                {
                    if(!$ResStock->updated)
                        $errors[] = $ResStock;

                    if($errors) $this->log('error', 'downwardIndex', 'array errors', $stocksToImport, $errors);
                };
            }else{
                $this->log('error', 'downwardIndex', 'POST /v1/product/import/stocks', $stocksToImport, $res);
            };
        }

        return (object) ['totalCount' => $count, 'totalPriceIndex' => $allPrice/$count];
    }


    public function productImportStocksV1($stocksToImport, $fname = 'productImportStocksV1'): \stdClass
    {
        $stocksToImportParts = array_chunk($stocksToImport, 100);
        $totalRes = [];

        foreach($stocksToImportParts as $stocksToImportPart)
        {
            $errors = [];
            $res = $this->makeRequest(
                'POST',
                '/v1/product/import/stocks',
                ['stocks' => $stocksToImportPart]
            );

            if(isset($res->result) and (count($res->result) > 0))
            {
                $this->log('info', $fname, 'POST /v1/product/import/stocks', $stocksToImportPart, $res);

                foreach($res->result as $ResStock)
                {
                    if(!$ResStock->updated)
                        $errors[] = $ResStock;

                    if($errors) $this->log('error', $fname, 'array errors', $stocksToImportPart, $errors);
                };

                $totalRes = array_merge($totalRes, $res->result);
            }else{
                $this->log('error', $fname, 'POST /v1/product/import/stocks', $stocksToImportPart, $res);
            };
        }

        $res = new \stdClass();
        $res->result = $totalRes;
        return $res;
    }


    public function checkRemoveStopStockByMaxIndices()
    {
        $removeStopStockWithDate = Ozon::getMaxIndex($this->shopId)->displayDatedGoods;
        $stopStockRemovedCount = 0;

        if($removeStopStockWithDate)
        {
            $stopStocks = SystemsProductsStop::where([
                ['stop_stock', 1],
                ['orders_type_shop_id', $this->shopId]
            ])->where(function($q){
                $q->whereBetween('stop_stock_until', ['2021-01-01', '2021-01-02'])
                    ->orWhereBetween('stop_stock_until', ['2022-01-01', '2022-01-02'])
                        ->orWhereBetween('stop_stock_until', ['2023-01-01', '2023-01-02'])
                            ->orWhereBetween('stop_stock_until', ['2024-01-01', '2024-01-02']);
            })->get();

            foreach($stopStocks as $StopStock)
            {
                $StopStock->stop_stock = 0;
                $StopStock->user_id = 0;
                $StopStock->save();
                $stopStockRemovedCount++;
                //$this->log('warning', 'checkRemoveStopStockByMaxIndices', "set stop-stock = 0");
            }

            if($stopStockRemovedCount > 0)
                $this->log('info', 'checkRemoveStopStockByMaxIndices', "stop-stock removed count = $stopStockRemovedCount");
        }
    }


    public function getActionsList($actionId = false, $test = false)
    {
        $res = $this->makeRequest(
            'GET',
            '/v1/actions'
        );

        $actions = $res->result ?? [];

        if($actionId)
        {
            foreach($actions as $Action)
            {
                if($Action->id === $actionId)
                {
                    $actions = [];
                    $actions[] = $Action;
                }
            }
        }

        return $actions;
        // 5236
    }

    public function getActionsCandidates($actionId, $offset = 0, $test = false)
    {
        $limit = 1000;
        $req = [
            'action_id' => $actionId,
            'offset' => $offset,
            'limit' => $limit
        ];

        $res = $this->makeRequest(
            'POST',
            '/v1/actions/candidates',
            $req
        );

        $actionCandidates = $res->result->products ?? [];
        $total = $res->result->total ?? false;

        if(($actionCandidates and $total) and ($total - ($offset + $limit)) > 0)
        {
            $actionCandidates = array_merge($actionCandidates, $this->getActionsCandidates($actionId, ($offset + $limit)));
        }else
        {
            return $actionCandidates;
        }

        // next is temp for exclude error with double products in productsAction
        $actionProducts = $this->getActionsProducts($actionId);

        if($actionCandidates and $actionProducts)
        {
            foreach($actionCandidates as $actionCandidateKey => $ActionCandidate)
            {
                foreach($actionProducts as $actionProductKey => $ActionProduct)
                {
                    if($ActionCandidate->id === $ActionProduct->id)
                    {
                        unset($actionCandidates[$actionCandidateKey]);
                        //unset($actionProducts[$actionProductKey]); can't unset href to this array
                    }
                }
            }
        }

        return $actionCandidates;
    }

    /*
    public function getActionsCandidates($actionId, $test = false)
    {
        $req = [
            'action_id' => $actionId
        ];

        $res = $this->makeRequest(
            'POST',
            '/v1/actions/candidates',
            $req
        );

        if($test) dd($res);

        // next is temp for exclude error with double products in productsAction
        $actionCandidates = isset($res->result->products)?$res->result->products:[];
        $actionProducts = $this->getActionsProducts($actionId);

        if($actionCandidates and $actionProducts)
        {
            foreach($actionCandidates as $actionCandidateKey => $ActionCandidate)
            {
                foreach($actionProducts as $actionProductKey => $ActionProduct)
                {
                    if($ActionCandidate->id === $ActionProduct->id)
                    {
                        unset($actionCandidates[$actionCandidateKey]);
                        //unset($actionProducts[$actionProductKey]);
                    }
                }
            }
        }

        return $actionCandidates;
    }
    */

    private $actionsProducts = [];
    /*
    public function getActionsProducts($actionId)
    {
        if(isset($this->actionsProducts[$actionId]))
        {
            return $this->actionsProducts[$actionId];
        }else{
            $req = ['action_id' => $actionId];

            $res = $this->makeRequest(
                'POST',
                '/v1/actions/products',
                $req
            );

            $actionProducts = isset($res->result->products)?$res->result->products:[];
            if($actionProducts)
                $this->actionsProducts[$actionId] = $actionProducts;

            return $actionProducts;
        }
    }
    */

    public function getActionsProducts($actionId, $offset = 0)
    {
        $limit = 1000;
        if(isset($this->actionsProducts[$actionId]))
        {
            return $this->actionsProducts[$actionId];
        }else{
            $req = [
                'action_id' => $actionId,
                'offset' => $offset,
                'limit' => $limit,
            ];

            $res = $this->makeRequest(
                'POST',
                '/v1/actions/products',
                $req
            );

            $actionProducts = $res->result->products ?? [];
            $total = $res->result->total ?? false;

            if(($actionProducts and $total) and ($total - ($offset + $limit)) > 0)
            {
                $actionProducts = array_merge($actionProducts, $this->getActionsProducts($actionId, ($offset + $limit)));
            }else
            {
                return $actionProducts;
            }

            if($actionProducts)
                $this->actionsProducts[$actionId] = $actionProducts;

            return $actionProducts;
        }
    }

    public function activateActionProduct($actionId, $products)
    {
        $req = [
            'action_id' => $actionId,
            'products' => $products
        ];

        $res = $this->makeRequest(
            'POST',
            '/v1/actions/products/activate',
            $req
        );

        if(isset($res->result->product_ids))
        {
            $this->log('info', 'activateActionProduct', 'activate', $req, $res);
            return $res->result->product_ids;
        }else{
            $this->log('error', 'activateActionProduct', '', $req, $res);
            return false;
        }
    }

    public function deactivateActionProduct($actionId, $productIds)
    {
        $req = [
            'action_id' => $actionId,
            'product_ids' => $productIds
        ];

        $res = $this->makeRequest(
            'POST',
            '/v1/actions/products/deactivate',
            $req
        );

        if(isset($res->result->product_ids))
        {
            //$this->log('info', 'deactivateActionProduct', 'deactivate', $req, $res);
            return $res->result->product_ids;
        }else{
            $this->log('error', 'deactivateActionProduct', '', $req, $res);
            return false;
        }
    }

    public function checkActionsProducts()
    {
        $start = microtime(true);

        $actionsList = $this->getActionsList();
        $desiredList = [];
        foreach($actionsList as $Action)
        {
            $dateStart = Carbon::now()->setTimezone('Europe/Moscow')->startOfDay();
            $dateEnd = Carbon::now()->addDays(30)->setTimezone('Europe/Moscow')->endOfDay();

            $actionDateStart = Carbon::parse($Action->date_start)->setTimezone('Europe/Moscow');
            $actionDateEnd = Carbon::parse($Action->date_end)->setTimezone('Europe/Moscow');

            if(!in_array($Action->id, [166177, 166426]))
                if($actionDateStart > $dateEnd OR $actionDateEnd < $dateStart) // only 1 week period
                    continue;

            $desiredList[] = $Action;
        }

        unset($actionsList);

        //$this->log('info', 'checkActionsProducts', "Actions in period 7 days: ".count($desiredList));

        $totalToActivate = 0;
        $toActivate = [];
        $totalToDeactivate = 0;
        $toDeactivate = [];
        $changedProducts = 0;
        foreach($desiredList as $Action)
        {
            if($Action->action_type === 'NTH_FOR_FREE') continue;

            $actionProducts = $this->getActionsProducts($Action->id);
            if(!$actionProducts) continue;

            foreach($actionProducts as $ActionProduct)
            {
                if($Action->id === 9981 and $ActionProduct->id === 19213785) dd($ActionProduct);

                continue;
                if($ActionProduct->action_price !== $ActionProduct->max_action_price)
                {
                    if($ActionProduct->action_price < $ActionProduct->max_action_price)
                    {
                        $ProductSet = new \stdClass();
                        $ProductSet->product_id = $ActionProduct->id;
                        $ProductSet->action_price = $ActionProduct->max_action_price;
                        $toActivate[] = $ProductSet;
                        $totalToActivate++;
                    }else if($ActionProduct->action_price > $ActionProduct->max_action_price)
                    {
                        $toDeactivate[] = $ActionProduct->id;
                        $totalToDeactivate++;
                    };

                    $changedProducts++;
                }
            }

            if(count($toDeactivate) > 0)
            {
                $success = $this->deactivateActionProduct($Action->id, $toDeactivate);
                $this->log($success?'info':'error', 'checkActionsProducts', 'toDeactivate '.$Action->id, $toDeactivate);
            }

            if(count($toActivate) > 0)
            {
                $success = $this->activateActionProduct($Action->id, $toActivate);
                $this->log($success?'info':'error', 'checkActionsProducts', 'toActivate '.$Action->id, $toActivate);
            }

            $toDeactivate = [];
            $toActivate = [];

        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        $this->log('info', 'checkActionsProducts', "Changed: $changedProducts. $ExecutionTime, totalToActivate: $totalToActivate, totalToDeactivate: $totalToDeactivate");
    }

    public function productsMatches()
    {
        $start = microtime(true);
        $ozonProducts = Ozon::getAllProducts($this->shopId);

        $updated = 0;

        $keyCode = uniqid();

        foreach($ozonProducts as $key => $OzonProduct)
        {
            if($Product = Products::getProductBy('sku', $OzonProduct->offer_id))
            {
                $TypeShopProduct = TypeShopProduct::firstOrNew([
                    'type_shop_id' => $this->shopId,
                    'product_id' => $Product->id,
                ]);
                $TypeShopProduct->shop_product_id = $OzonProduct->product_id;
                $TypeShopProduct->key_code = $keyCode;
                $TypeShopProduct->save();
                $updated++;
            }else{
                $this->log('error', 'productsMatches', "Unknown product sku $OzonProduct->offer_id");
            }

            print_r($key.' of '.count($ozonProducts)."\r");
        }

        TypeShopProduct::where([
            ['type_shop_id', $this->shopId],
        ])->where(function($q) use ($keyCode)
        {
            $q
                ->where('key_code', '!=', $keyCode)
                ->orWhereNull('key_code');

        })->delete();

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        $this->log('info', 'productsMatches', "Updated: $updated. $ExecutionTime");
    }

    public function removeFromActionsUnavailable()
    {
        $start = microtime(true);

        $actionsList = $this->getActionsList();
        foreach($actionsList as $Action)
        {
            $dateStart = Carbon::now()->setTimezone('Europe/Moscow')->startOfDay();
            $dateEnd = Carbon::now()->addDays(30)->setTimezone('Europe/Moscow')->endOfDay();

            $actionDateStart = Carbon::parse($Action->date_start)->setTimezone('Europe/Moscow');
            $actionDateEnd = Carbon::parse($Action->date_end)->setTimezone('Europe/Moscow');

            if(!in_array($Action->id, [166177, 166426]))
                if($actionDateStart > $dateEnd OR $actionDateEnd < $dateStart) // only 1 month period
                    continue;

            $desiredList[] = $Action;
        }

        unset($actionsList);

        $totalToDeactivate = 0;
        $toDeactivate = [];

        foreach($desiredList as $Action)
        {
            $actionProducts = $this->getActionsProducts($Action->id);
            if(!$actionProducts) continue;

            foreach($actionProducts as $ActionProduct)
            {
                $Product = TypeShopProduct::where([
                    ['shop_product_id', $ActionProduct->id],
                    ['type_shop_id', $this->Warehouse['typeShopId']]
                ])->first()->product??false;

                if($Product)
                {
                    $actionProductPseudoQuantity = Ozon::actionProductPseudoQuantity($Product, $this->shopId);

                    if($actionProductPseudoQuantity === 0)
                    {
                        $toDeactivate[] = $ActionProduct->id;
                        $totalToDeactivate++;
                    }
                }else{
                    $toDeactivate[] = $ActionProduct->id;
                    $totalToDeactivate++;
                }
            }

            if(count($toDeactivate) > 0)
            {
                $success = $this->deactivateActionProduct($Action->id, $toDeactivate);
                $this->log(
                    $success?'info':'error',
                    'removeFromActionsUnavailable',
                    'toDeactivate ActionId:'.$Action->id,
                    $toDeactivate
                );
            }

            $toDeactivate = [];
        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        $this->log('info', 'removeFromActionsUnavailable', "TotalToDeactivate: $totalToDeactivate. $ExecutionTime");
    }

    public function updateActions()
    {
        $actionsList = $this->getActionsList();
        foreach($actionsList as $Action)
        {
            $OzonAction = OzonAction::firstOrNew([
                'shop_id' => $this->shopId,
                'action_id' => $Action->id
            ]);

            $OzonAction->title = $Action->title;
            $OzonAction->date_start = Carbon::parse($Action->date_start)->setTimezone('Europe/Moscow');
            $OzonAction->date_end = Carbon::parse($Action->date_end)->setTimezone('Europe/Moscow');
            $OzonAction->save();
        }
    }

    public function warehouseList()
    {
        $res = $this->makeRequest(
            'POST',
            "/v1/warehouse/list",
            ['hello' => '123'] // there is error, if no body
        );

        dd($res);
        // Ritmz - 15682964066000

        //POST /v1/warehouse/list
    }

    public function deliveryMethodList()
    {
        $res = $this->makeRequest(
            'POST',
            "/v1/delivery-method/list",
            ['filter' => [
                'warehouse_id' => 15682964066000, // there is need NEW WarehouseId
                'status' => 'ACTIVE',
            ]] // there is error, if no body
        );

        dd($res);
    }

    public function actCreate() // -
    {
        $res = $this->makeRequest(
            'POST',
            "/v2/posting/fbs/act/create",
            ['hello' => '123'] // there is error, if no body
        );

        dd($res);
    }

    public function postingFbsCancel($systemOrderId, $cancelReasonId, $statusReason = '')
    {
        $res = $this->makeRequest(
            'POST',
            '/v2/posting/fbs/cancel',
            [
                'cancel_reason_id' => $cancelReasonId,
                'cancel_reason_message' => $statusReason,
                'posting_number' => $systemOrderId,
            ]
        );

        return $res->error->message ?? false;
    }

    public function postingFbsShip($systemOrderId)
    {
        $OzonOrder = $this->getOrder($systemOrderId);
        if($OzonOrder->status !== 'awaiting_packaging')
            return 'Внимание! Статус заказа не соответствует для перевода в новый статус! Необходимо проверить его на площадке, возможно это отмена или он уже собран!';

        $Order = Orders::getOrder($systemOrderId, false, $this->shopId);

        // forming ozon order products to send status
        $ozonOrderProducts = [];
        $items = [];
        foreach($OzonOrder->products as $OzonOrderProduct)
        {
            $OOP = new \stdClass();
            $OOP->offer_id = $OzonOrderProduct->offer_id;
            $OOP->sku = $OzonOrderProduct->sku;
            $OOP->quantity = $OzonOrderProduct->quantity;
            $OOP->quantityCheck = $OzonOrderProduct->quantity;
            $ozonOrderProducts[] = $OOP;

            $Item = new \stdClass();
            $Item->product_id = $OzonOrderProduct->sku;
            $Item->quantity = $OzonOrderProduct->quantity;
            $Item->exemplar_info = [
                'mandatory_mark' => '',
                'gtd' => '',
                'is_gtd_absent' => true,
                'is_rnpt_absent' => true,
                'rnpt' => '',
            ];
            $items[] = $Item;
        }

        // check quantity for all order with all packages
        foreach($ozonOrderProducts as $OOP)
        {
            foreach($Order->products as $OrderProduct)
            {
                if($OOP->offer_id === $OrderProduct->product->sku)
                {
                    $OOP->quantityCheck -= $OrderProduct->product_quantity;
                }
            }
        }

        // check if quantity not equal / hasn't products
        foreach($ozonOrderProducts as $OOP)
        {
            if($OOP->quantityCheck !== 0)
                return "Внимание! Количество товара {$OOP->offer_id} в заказе не совпадает между Площадкой и CRM. Необходимо сравнить заказ с площадкой вручную!";
        }

        // if all ok - send status change

        $res = $this->makeRequest(
            'POST',
            "/v3/posting/fbs/ship",
            [
                'posting_number' => $systemOrderId,
                'packages' =>
                [
                    'products' => $items
                ]
            ]
        );

        return $res->error->message ?? false;
    }

    public function orderSetStatus($systemOrderId, $status, $statusReason = '')
    {

        if(in_array($status, ['delivering', 'last-mile', 'delivered']))
        {
            //POST /v2/fbs/posting/delivering — изменяет статус на Доставляется.
            //POST /v2/fbs/posting/last-mile — изменяет статус на Курьер в пути.
            //POST /v2/fbs/posting/delivered — изменяет статус на Доставлен.

            $res = $this->makeRequest(
                'POST',
                "/v2/fbs/posting/$status",
                [
                    'posting_number' => $systemOrderId
                ]
            );

            return $res->error->message ?? false;
        }else
        {
            //POST /v3/posting/fbs/ship . alias: awaiting_deliver - собрать заказ (Ожидает отгрузки)
            //POST /v2/posting/fbs/cancel . alias: cancelled - собрать заказ (Отменён)
            $arrStatus = explode('-', $status);
            $mainStatus = $arrStatus[0];
            $cancelReasonId = $arrStatus[1];
            switch($mainStatus)
            {
                case 'awaiting_deliver': return $this->postingFbsShip($systemOrderId);
                case 'cancelled': return $this->postingFbsCancel($systemOrderId, $cancelReasonId, $statusReason);
            }
        }


        return "Unknown Ozon status: $status";
    }

    public function getCancelReasonList()
    {
        return $this->makeRequest(
            'POST',
            "/v2/posting/fbs/cancel-reason/list"
        );
    }

    public function orderTrackingNumberSet($systemOrderId, $track)
    {
        $trackingNumbers = [];
        $TrackingNumber = new \stdClass();
        $TrackingNumber->posting_number = $systemOrderId;
        $TrackingNumber->tracking_number = $track;
        $trackingNumbers[] = $TrackingNumber;
        $req = ['tracking_numbers' => $trackingNumbers];

        $res = $this->makeRequest(
            'POST',
            "/v2/fbs/posting/tracking-number/set",
            $req
        );

        if(isset($res->result[0]->result) and ($res->result[0]->result === false))
        {
            return $res->result[0]->error??'Не описания ошибки.';
        }else{
            return false;
        }
    } // +

    private $stockSent = false;
    public function updateStocks($stocksToImport)
    {
        $ozonWarehouses = Ozon::getOzonWarehousesByShopId($this->shopId);

        foreach($ozonWarehouses as $ozonWarehouseId)
        {
            // This need to set Zero quantity to stocks
            if($ozonWarehouseId === '23705943260000') // from 2022-07-04 21:23
            {
                foreach($stocksToImport as $StockToImport)
                {
                    $StockToImport->stock = 0;

                    /*
                    if($this->stockSent)
                    {
                        $StockToImport->stock = 0;
                    }else
                    {
                        if($StockToImport->stock and ($StockToImport->stock > 0))
                        {
                            $StockToImport->stock = 1;
                            $this->stockSent = true;
                        }
                    }
                    */
                }
            }
            //*/

            foreach($stocksToImport as $StockToImport)
            {
                $StockToImport->warehouse_id = $ozonWarehouseId;
            }

            $res = $this->makeRequest(
                'POST',
                '/v2/products/stocks',
                ['stocks' => $stocksToImport]
            );
        }

        return $res??false;
    }

    public function arrayAddProductInfo(&$products, $key = 'id')
    {
        $actionProductsIds = array_column($products, $key);
        $productsInfo = $this->getProductsInfo($actionProductsIds, 'product_id');

        foreach($products as $OzonProduct)
        {
            foreach($productsInfo as $ProductInfo)
            {
                if($OzonProduct->id === $ProductInfo->id)
                {
                    $OzonProduct->productInfo = $ProductInfo;
                }
            }
        }
    }

    public function getStockV3($sku, $getNewFromAPI = true)
    {
        if($stocks = $this->getStocksV3(['offer_id' => [$sku]], $getNewFromAPI))
            foreach($stocks as $productStocks)
            {
                if($productStocks->offer_id === $sku)
                {
                    foreach($productStocks->stocks as $key => $Stock)
                    {
                        if(
                            ($Stock->type === 'fbo' and (Shops::getParentShop($this->shopId) === 74))
                            or
                            ($Stock->type === 'fbs' and (Shops::getParentShop($this->shopId) !== 74))
                        )
                        {
                            $productStocks->stock = $productStocks->stocks[$key];
                            return $productStocks;
                        }
                    }

                    return false;
                }
            }

        return false;
    }

    public $productsStocksV3 = array();
    public function getStocksV3($filter = false, $getNewFromAPI = true)
    {
        $limit = 1000;
        $lastId = false;

        if(!$filter or !$getNewFromAPI)
        {
            $filter =  [
                'visibility' => 'ALL'
            ];
        }

        if(empty($this->productsStocksV3) or $getNewFromAPI)
        {
            $this->productsStocksV3 = [];
            $stopper = false;
            $i = 0;
            while (!$stopper)
            {
                $req = [
                    'filter' => $filter,
                    'limit' => $limit
                ];
                if($lastId) $req['last_id'] = $lastId;

                $res = $this->makeRequest(
                    'POST',
                    "/v3/product/info/stocks",
                    $req
                );
                if(isset($res->result->items)) {
                    if(count($res->result->items) > 0) {
                        $this->productsStocksV3 = array_merge($this->productsStocksV3, $res->result->items);
                        $lastId = $res->result->last_id;
                    } else {
                        $stopper = true;
                    };
                    if(count($res->result->items) < 1000) $stopper = true;

                    $i++;
                    if($i === 10) {
                        $stopper = true;
                        $this->log('error', 'getStocksV3', 'Ozon getStocksV3 stopper!', $req, $res);
                        die('Ozon getPrices stopper!');
                    };
                } else {
                    $stopper = true;
                    $this->log('error', 'getStocksV3', 'POST /v3/product/info/stocks', $req, $res);
                }
            };
        };

        return $this->productsStocksV3;
    }

    public function returns()
    {
        $res = $this->makeRequest(
            'POST',
            '/v2/returns/company/fbs',
            [
                'filter' => [
                    'posting_number' => '32626240-0320-1'
                ]
            ]
        );

        dd($res);
    }

    public function reportStockCreate()
    {
        $res = $this->makeRequest(
            'GET',
            '/v1/report/file/c3/d1/c3d15d880ab54115.csv',
            NULL,
            true
        );

        dd($res);
    }

    public function getReturns($offset = 0)
    {
        $returns = [];
        $limit = 1000;
        $res = $this->makeRequest(
            'POST',
            '/v2/returns/company/fbs',
            [
                'limit' => $limit,
                'offset' => $offset,

                'filter' => [
                    'accepted_from_customer_moment' => [
                        //'time_from' => '2019-08-24T14:15:22Z',
                        'time_from' => Carbon::now()->subYear()->setTimezone('Europe/Moscow')->format('Y-m-d\TH:i:s\Z'),
                        'time_to' => Carbon::now()->setTimezone('Europe/Moscow')->format('Y-m-d\TH:i:s\Z'),
                    ]
                ]
            ]
        );

        if(isset($res->result) and (isset($res->result->returns) and $res->result->returns))
        {
            $returns = $res->result->returns;
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
            if(!$OzonFbsReturn = OzonFbsReturn::where('ozon_return_id', $Return->id)->first())
            {
                $OzonFbsReturn = new OzonFbsReturn;
                $exists = false;
            }

            $OzonFbsReturn->shop_id = $this->shopId;
            $OzonFbsReturn->ozon_return_id = $Return->id;
            $OzonFbsReturn->clearing_id = $Return->clearing_id;
            $OzonFbsReturn->posting_number = $Return->posting_number;
            $OzonFbsReturn->ozon_product_id = $Return->product_id;
            $OzonFbsReturn->sku = $Return->sku;

            if($Product = Products::getProductBy('sku', $Return->sku))
                $OzonFbsReturn->product_id = $Product->id;

            if($TypeShopProduct = TypeShopProduct::where([
                ['type_shop_id', $this->shopId],
                ['shop_product_id', $Return->product_id],
            ])->first()) $OzonFbsReturn->product_id = $TypeShopProduct->product_id;

            $OzonFbsReturn->status = $Return->status;
            $OzonFbsReturn->commission = $Return->commission;
            $OzonFbsReturn->commission_percent = $Return->commission_percent;
            $OzonFbsReturn->is_moving = $Return->is_moving;
            $OzonFbsReturn->is_opened = $Return->is_opened;
            $OzonFbsReturn->last_free_waiting_day = $Return->last_free_waiting_day;
            $OzonFbsReturn->place_id = $Return->place_id;
            $OzonFbsReturn->moving_to_place_name = $Return->moving_to_place_name;
            $OzonFbsReturn->picking_amount = $Return->picking_amount;
            $OzonFbsReturn->price = $Return->price;
            $OzonFbsReturn->price_without_commission = $Return->price_without_commission;
            $OzonFbsReturn->product_name = $Return->product_name;
            $OzonFbsReturn->quantity = $Return->quantity;
            $OzonFbsReturn->return_date = $Return->return_date;
            $OzonFbsReturn->return_reason_name = $Return->return_reason_name;
            $OzonFbsReturn->waiting_for_seller_date_time = $Return->waiting_for_seller_date_time;
            $OzonFbsReturn->returned_to_seller_date_time = $Return->returned_to_seller_date_time;
            $OzonFbsReturn->waiting_for_seller_days = $Return->waiting_for_seller_days;
            $OzonFbsReturn->returns_keeping_cost = $Return->returns_keeping_cost;
            $OzonFbsReturn->accepted_from_customer_moment = $Return->accepted_from_customer_moment;

            if($OzonFbsReturn->save())
            {
                $saleHref = $OzonFbsReturn->posting_number;
                if($Order = Orders::getOrder($OzonFbsReturn->posting_number, false, $this->shopId))
                {
                    if($Sale = $Order->sale??false)
                    {
                        $OzonFbsReturn->sale_id = $Sale->id;
                        $OzonFbsReturn->save();
                        $saleHref = "<a target = '_blank' href = '$Sale->EditReturnsPath'>$Sale->OrderNumber</a>";
                    }
                }

                if(!$exists)
                {
                    Notifications::new(
                        'Новый Ozon FBS возврат',
                        "По заказу $saleHref получен новый возврат. Требуется вернуть остатки.",
                        [1, 3]
                    );
                }
            }
        }
    }

    public function productsDeleteV2($skus)
    {
        $q = [
          'products' => $skus // "offer_id": "033"
        ];

        $res = $this->makeRequest(
            'POST',
            '/v2/products/delete',
            $q
        );

        dd($res);
    }

    public function productsArchiveV1($productIds)
    {
        $q = [
            'product_id' => $productIds
        ];

        $res = $this->makeRequest(
            'POST',
            '/v1/product/archive',
            $q
        );

        return $res->result??false;
    }

    public function getTransactionsV3($fromDate, $toDate = false, $postingNumber = false, $page = 1, $pageSize = 1000): array
    {
        $transactions = [];

        $q = [
            'filter' => [
            ],
            'page' => $page,
            'page_size' => $pageSize,
        ];

        if($fromDate) $q['filter']['date']['from'] = $fromDate;
        if($toDate) $q['filter']['date']['to'] = $toDate;
        if($postingNumber) $q['filter']['posting_number'] = $postingNumber;



        $res = $this->makeRequest(
            'POST',
            '/v3/finance/transaction/list',
            $q
        );

        if(isset($res->result->operations) and !empty($res->result->operations))
        {
            $transactions = $res->result->operations;
            if(count($transactions) === $pageSize)
            {
                $transactions = array_merge($transactions, $this->getTransactionsV3($fromDate, $toDate, $postingNumber, $page + 1, $pageSize));
            }
        }

        return $transactions;
    }

    public function productPicturesImportV1($shopProductId, $imagesUrls)
    {
        $res = $this->makeRequest(
            'POST',
            '/v1/product/pictures/import',
            [
                'product_id' => $shopProductId,
                'images' => $imagesUrls,
            ]
        );

        if(isset($res->result) and $res->result)
        {
            return true;
        }else
        {
            return false;
        }
    }

    public function actionsHotsalesList()
    {

        $res = $this->makeRequest(
            'POST',
            '/v1/actions/hotsales/list'
        );

        dd($res); // "hotsale_id": 38
    }

    public function actionsHotsalesProducts($hotsaleId)
    {
        $res = $this->makeRequest(
            'POST',
            '/v1/actions/hotsales/products',
            [
                'hotsale_id' => $hotsaleId,
                'limit' => 100
            ]
        );

        dd($res);
    }

    public function v3ProductInfoLimit()
    {
        $res = $this->makeRequest(
            'POST',
            '/v3/product/info/limit',
            [
            ]
        );

        if(isset($res->result) and $res->result)
        {
            return $res->result;
        }else
        {
            dump($res);
            return false;
        }
    }



    // POST/


    /*
     POST/v1/actions/hotsales/list - получить список список доступных акций Hot Sale
Запрос:
{}
POST/v1/actions/hotsales/products - получить список товаров, которые могут участвовать в акции или уже участвуют в акции Hot Sale
Запрос:

{ "hotsale_id": 0, "limit": 0, "offset": 0 }
POST/v1/actions/hotsales/activate - добавить товары в акцию Hot Sale
Запрос:
{
"hotsale_id": 0,
"products": [

{ "action_price": 0, "product_id": 0, "stock": 0 }
]
}
POST/v1/actions/hotsales/deactivate - удалить товары из акции Hot Sale
Запрос:

{ "hotsale_id": 0, "product_ids": [ 0 ] }
     */

}
