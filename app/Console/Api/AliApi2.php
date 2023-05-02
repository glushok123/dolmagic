<?php

namespace App\Console\Api;

use App\Eloquent\Products\Product;
use App\Eloquent\Products\TypeShopProduct;
use App\Eloquent\Shops\ShopBrandName;
use App\Eloquent\Warehouse\TypeShopWarehouse;
use App\Models\Prices\Price;
use App\Models\Products;

class AliApi2 extends Api
{
    public $systemId = 8; // Aliexpress = 8
    public $shopId = 8;
    public $token = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzZWxsZXJfaWQiOjk4ODA2ODAxOSwidG9rZW5faWQiOjMzODB9.G0nIjHz2T62Z-Nj_H-ZOOtrvEpVjjIN6gsEA9XczCOKHM_0ckkvbY59dtkLI9zP3ofcgnGacctIZEN7Q7BP4R3EBon_DbHg_pbq_2W1xVzPrfTyuLwzludaNFovRwlZWrTjKr_WlAdUBjPPglERmQkmG3Tb15LWPQ2i7ZgH0Fj4aOXn-shTRSj-yoaSZJkxv5UnkMjZQDwNYQ3ecnOUjGO1yBSkKlR4WvjlMQFetDYH3G0odBjW5xPzbspfFq57jM2ERlk0WLdqAYgApByMWj_yzSxoR_hnRW0OgjQ_4Myo5FGBOrhU3WyjoKajPvgavCERxjnih18n6VWHdBOuy96mOQ3Dwq8s1XSqNZJ34h3e1HsdvHk7Dc5llF-itU0tRfXcvv-K_8rXOHUXihRbz6Qlzikj8QomHCcYyTe6GBGz_LsbVFt0aoStvaHC-J82DI0MZ1gH7H7MoyeP9O0eBvU3MtmXYG6LlsmlPnme8kluP5ViCFmStN5wv3y6eCyk0v8IUmLOvG_0zCv2YbFK4Ffe2sMFwQkKEQCxUo0XFsmYWi-zuQaUf-waRG7PcM80tLgLfUW6Vzy00-fQw6vjxADJKHw_zPvSxTS3Oa44D-a108N6Soty5OR1JEIonCk-kC_SQTZkMPrvQtTv2A4x2kEdkOYx9IODy9LyzTuNDrJ4';
    public $host = 'https://openapi.aliexpress.ru'; // Host
    // https://business.aliexpress.ru/docs/api-token





    public $appKey = 32238155;
    public $appSecret = 'db57990757b8495296b32a2d6d790555';
    public $sessionKey = '50000700f15DWP0qaZzKwjnuDrQ135b1ba0Dbccr4sVVjlgOGxmyw9i0Aoq21EOxF38';
    public $importUpPrice = 150;
    public $importUpPricePercent = 7;
    public $importUpPriceLimit = 1000; // this max price for up price
    public $tmallClient;

    // Session key = https://oauth.aliexpress.com/authorize?response_type=token&client_id=32238155&state=1212&view=web&sp=ae

    // other
    //public $freight_template_id = 1014643005; // "1014643005" - dostavka1
    public $freight_template_id = 24036288309; // "24036288309" - Шаблон FBS. Пункт приёма Почта России 1
    public $header_module_id = 1005000000265762;
    public $footer_module_id = 1005000000267249;

    public function  __construct()
    {
        parent::__construct();

        $this->headers = array(
            'x-auth-token: '.$this->token,
            'Content-Type: application/json'
        );
    }

    public function getOrdersList()
    {
        $res = $this->makeRequest(
            'POST',
            '/seller-api/v1/order/get-order-list',
            [
                'date_start' => '2022-08-22T00:00:00Z',
                'page_size' => 10,
                'page' => 1
            ]
        );

        dd($res);
    }

    public function getStocks()
    {
        $res = $this->makeRequest(
            'POST',
            '/api/v1/stocks',
            [
                'stocks' =>
                [
                    ['sku' => '12000026369847728']
                ]
            ]
        );

        dd($res);
    }

    public function getProducts($lastId = false): array
    {
        $products = [];
        $req = ['limit' => 50];
        if($lastId)
            $req['last_product_id'] = $lastId;

        $res = $this->makeRequest(
            'POST',
            '/api/v1/scroll-short-product-by-filter',
            $req
        );

        if(isset($res->data) and !empty($res->data))
        {
            $products = array_merge($products, $res->data);
            if(count($res->data) === 50)
            {
                $LastProduct = end($res->data);
                $products = array_merge($products, $this->getProducts($LastProduct->id));
            }
        }

        return $products;
    }

    public function productsMatches()
    {
        $start = microtime(true);
        $shopProducts = $this->getProducts();

        $updated = 0;
        $keyCode = uniqid();

        if(count($shopProducts) > 0)
        {
            $countShopProducts = count($shopProducts);
            foreach($shopProducts as $key => $ShopProduct)
            {
                dump("$key / $countShopProducts");

                $sku = $ShopProduct->sku[0]->code??false;
                if($sku and $Product = Products::getProductBy('sku', $sku))
                {
                    $TypeShopProduct = TypeShopProduct::firstOrNew([
                        'type_shop_id' => $this->shopId,
                        'product_id' => $Product->id,
                    ]);
                    $TypeShopProduct->shop_product_id = $ShopProduct->id;
                    $TypeShopProduct->key_code = $keyCode;
                    $TypeShopProduct->save();
                    $updated++;
                }else{
                    $this->log('error', 'productsMatches', "Unknown product sku $sku");
                }
            }
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


    //POST /api/v1/product/update-sku-stock
    public function updateQuantity()
    {
        $start = microtime(true);

        $shopProducts = $this->getProducts();

        if(count($shopProducts) === 0)
        {
            dump('Nothing to update');
            return false;
        }

        $countShopProducts = count($shopProducts);
        $updates = [];
        foreach($shopProducts as $key => $ShopProduct)
        {
            dump("$key / $countShopProducts");

            $reqProduct = [
                'product_id' => $ShopProduct->id,
                'skus' => [],
            ];

            foreach($ShopProduct->sku as $ShopProductSku)
            {
                $quantity = 0;

                $sku = $ShopProductSku->code;
                if($sku and $Product = Products::getProductBy('sku', $sku))
                {

                    $Stop = Products::getSystemsProductsStopResult($Product, $this->shopId);
                    $quantity = $Stop->stock?0:($Product->shopAmounts($this->shopId)->amounts->balance??0);
                }

                $reqProduct['skus'][] = (object) [
                    'sku_code' => $sku,
                    'inventory' => (string) $quantity,
                ];
            }

            $updates[] = $reqProduct;
        }

        $parts = array_chunk($updates, 1000);
        foreach($parts as $part)
        {
            $req = [
                'products' => $part,
            ];
            $res = $this->makeRequest(
                'POST',
                '/api/v1/product/update-sku-stock',
                $req
            );
            //dd($req, $res);
        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        dump($ExecutionTime);
    }

    // POST /api/v1/product/update-sku-price
    public function updatePrice()
    {
        $start = microtime(true);

        $shopProducts = $this->getProducts();

        if(count($shopProducts) === 0)
        {
            dump('Nothing to update');
            return false;
        }

        $countShopProducts = count($shopProducts);
        $updates = [];
        foreach($shopProducts as $key => $ShopProduct)
        {
            dump("$key / $countShopProducts");

            $reqProduct = [
                'product_id' => $ShopProduct->id,
                'skus' => [],
            ];

            foreach($ShopProduct->sku as $ShopProductSku)
            {
                $sku = $ShopProductSku->code;
                if($sku and $Product = Products::getProductBy('sku', $sku))
                {
                    $Stop = Products::getSystemsProductsStopResult($Product, $this->shopId);
                    if(!$Stop->price)
                    {
                        if($Product->temp_old_price and ($Product->temp_old_price > $Product->temp_price))
                        {
                            $price = Price::recalculatePriceByUnloadingOption($Product->temp_old_price, $this->shopId);
                            $discountPrice = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->shopId);
                        }else{
                            $price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->shopId);
                            $discountPrice = false;
                        }

                        $reqPrice = (object) [
                            'sku_code' => $sku,
                            'price' => $price,
                        ];
                        if($discountPrice)
                            $reqPrice->discount_price = $discountPrice;

                        $reqProduct['skus'][] = $reqPrice;
                    }
                }
            }

            $updates[] = $reqProduct;
        }

        $parts = array_chunk($updates, 1000);
        foreach($parts as $part)
        {
            $req = [
                'products' => $part,
            ];
            $res = $this->makeRequest(
                'POST',
                '/api/v1/product/update-sku-price',
                $req
            );
            //dd($req, $res);
        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        dump($ExecutionTime);
    }

    public function getProductTemplate($Product): \stdClass
    {
        $Stop = Products::getSystemsProductsStopResult($Product, $this->shopId);

        $ProductToUpload = new \stdClass();

        $ProductToUpload->multi_language_description_list = [
            (object) [
                'language' => 'ru',
                'web' => $Product->temp_short_description,
                'mobile' => $Product->temp_short_description,
                'type' => 'html',
            ],
        ];

        $ProductToUpload->multi_language_subject_list = [
            (object) [
                'language' => 'ru',
                'subject' => $Product->name_ru,
            ],
        ];

        if($Product->name_eng)
        {
            $ProductToUpload->multi_language_subject_list[] = (object)
            [
                'language' => 'en',
                'subject' => $Product->name_eng,
            ];
        }

        $ProductToUpload->language = 'ru';

        if($Product->images and $imagesList = $this->getProductImages($Product))
        {
            $ProductToUpload->main_image_urls_list = $imagesList;
        }

        $ProductToUpload->package_length = $Product->category->temp_depth;
        $ProductToUpload->package_height = $Product->category->temp_height;
        $ProductToUpload->package_width = $Product->category->temp_width;

        $ProductToUpload->weight = (string) $Product->BoxSizes->valueWeightKg;

        $ProductToUpload->product_unit = '100000015'; // шт.
        $ProductToUpload->shipping_lead_time = 3;

        // sku_info_list
        $ProductToUpload->sku_info_list = [
            (object) [
                'sku_code' => $Product->sku,
                'price' => Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->shopId),
                'inventory' => $Stop->stock?0:($Product->shopAmounts($this->shopId)->amounts->balance??0),
            ]
        ];

        $ProductToUpload->brand_name = $this->getBrandNameByManufacturer($Product);
        $ProductToUpload->freight_template_id = $this->freight_template_id;
        $ProductToUpload->aliexpress_category_id = 2605; // 2605 - куклы

        return $ProductToUpload;
    }
    // POST /api/v1/product/create
    public function uploadProducts($products = [])
    {
        if(!$products)
        {
            $products = Product::whereDoesntHave('typeShopProducts', function ($q)
            {
                $q->where('type_shop_id', $this->shopId);
            })
                ->whereHas('amounts', function ($amounts)
                {
                    $amounts->where('available', '>', 0);
                    $amounts->whereIn('warehouse_id', TypeShopWarehouse::where('type_shop_id', $this->shopId)->pluck('warehouse_id')->toArray());
                })
                ->get();
        }

        $countProducts = count($products);

        if($countProducts === 0)
        {
            dd('Nothing to upload');
        }else
        {
            dump("$countProducts to upload");
        }

        $productsUploaded = '';
        foreach($products as $key => $Product)
        {
            dump("$key / $countProducts");

            if(empty($Product->temp_short_description))
            {
                $this->log('error', 'uploadProducts', "Product $Product->sku hasn't temp_short_description");
                continue;
            }

            $ProductToUpload = $this->getProductTemplate($Product);

            $res = $this->makeRequest(
                'POST',
                '/api/v1/product/create',
                [
                    'products' => [$ProductToUpload]
                ]
            );

            if(isset($res->results[0]->ok) and $res->results[0]->ok)
            {

            }else
            {
                dump("$Product->sku dont uploaded");
            }
        }

        if(count($products) > 0 )
            $this->log('info', 'uploadProducts', "Products uploaded: $productsUploaded");
    }

    public function getBrandNameByManufacturer($Product)
    {
        $brandName = 'None';
        if(isset($Product->manufacturer))
        {
            $ShopBrandName = ShopBrandName::where([
                ['shop_id', $this->shopId],
                ['name', $Product->manufacturer->name],
            ])->first();
            if($ShopBrandName) $brandName = $ShopBrandName->name;
        }

        return $brandName;
    }

    public function getProductImages($Product, $tmallProductId = false): array
    {

        $addAdditionalImage = true; // on / off
        $additionalImage = false;

        if($addAdditionalImage and $tmallProductId)
        {
            switch($this->shopId)
            {
                case 6: // Tmall
                    $additionalImage = "/images/temp/products-tmall/{$tmallProductId}_1.jpg";
                    break;
                case 8: // Aliexpress
                    $additionalImage = "/images/temp/products-aliexpress/{$tmallProductId}_1.jpg";
                    break;
            }

            if($additionalImage)
            {
                $path = '/var/www/crmdollmagic.ru/public'.$additionalImage;
                if(!file_exists($path)) $additionalImage = false;
            }
        }

        $images = [];

        if($additionalImage) $images[] = 'http://crmdollmagic.ru'.$additionalImage;

        $shopImages = Products::getShopImages($Product, $this->shopId);
        foreach($shopImages as $key => $ProductImage)
        {
            if($additionalImage)
            {
                if($key === 5) break; // There is no more than 5 images
            }else
            {
                if($key === 6) break; // There is no more than 6 images
            }

            $images[] = $ProductImage->url;
        }
        return $images;
    }

    // POST /api/v1/brand/get-brand-list
    public function getBrandsNames($offset = 0, $limit = 50): array
    {
        $brands = [];
        $res = $this->makeRequest(
            'POST',
            '/api/v1/brand/get-brand-list',
            [
                'offset' => $offset,
                'limit' => $limit,
                'filters' =>
                [
                    'filters' => 'approved'
                ]
            ]
        );

        if(isset($res->data->list) and $res->data->list)
        {
            $brands = array_merge($brands, $res->data->list);
            if(count($res->data->list) === 50)
            {
                $brands = array_merge($brands, $this->getBrandsNames($offset+$limit));
            }
        }

        return $brands;
    }

    public function saveBrandsNames()
    {
        $brandList = $this->getBrandsNames();

        if(!empty($brandList))
        {
            ShopBrandName::where('shop_id', $this->shopId)
                ->update(['state' => 0]);

            foreach($brandList as $Brand)
            {
                if(isset($Brand->name) and isset($Brand->id))
                {
                    $ShopBrandName = ShopBrandName::firstOrNew([
                        'shop_id' => $this->shopId,
                        'name' => $Brand->name,
                        'system_brand_id' => $Brand->id,
                    ]);
                    $ShopBrandName->state = 1;
                    $ShopBrandName->save();
                }
            }
        }
    }


    // POST /api/v1/product/edit
    public function updateProducts($products = [], $test = false)
    {
        $start = microtime(true);

        if(!$products)
        {
            $products = Product::whereHas('typeShopProducts', function ($q)
            {
                $q->where('type_shop_id', $this->shopId);
            })
                ->whereHas('amounts', function ($amounts){
                    $amounts->where('available', '>', 0);
                    $amounts->whereIn('warehouse_id', TypeShopWarehouse::where('type_shop_id', $this->shopId)->pluck('warehouse_id')->toArray());
                })
                ->get();
        }

        $countProducts = count($products);
        if($countProducts === 0) dd('Nothing to update');

        $productsToUpdate = [];
        foreach($products as $key => $Product)
        {
            dump("$key / $countProducts");

            if(!$tmallProductId = $Product->typeShopProducts->where('type_shop_id', $this->shopId)->first()->shop_product_id)
                continue;

            $ProductToUpdate = $this->getProductTemplate($Product);
            $ProductToUpdate->product_id = $tmallProductId;

            $productsToUpdate[] = $ProductToUpdate;
        }

        if($productsToUpdate)
        {
            $parts = array_chunk($productsToUpdate, 1000);
            foreach($parts as $part)
            {
                $res = $this->makeRequest(
                    'POST',
                    '/api/v1/product/edit',
                    [
                        'products' => $part
                    ]
                );
            }
        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        dump($ExecutionTime);
    }
}
