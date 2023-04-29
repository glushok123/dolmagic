<?php

namespace App\Models\Others\Insales;


use App\Console\Api\InsalesApi;
use App\Eloquent\Insales\InsalesProductsPrice;
use App\Eloquent\Products\Product;
use App\Eloquent\Products\TypeShopProduct;
use App\Models\Model;
use App\Models\Prices\Price;
use App\Models\Products;
use Carbon\Carbon;


class Insales extends Model
{
    public static function changeYMLOfferProducts($Offer)
    {
        $sku = trim((string) $Offer->vendorCode);
        switch($sku)
        {
            case '559894':
            case '572411':
                    $Offer->categoryId = '000000'.'0';
                break;

            case 'GLH45':
                $Offer->categoryId = '000000'.'1';
                break;

            case 'LOHS-UA1-975HG':
            case 'LOHS-UA1-579SP':
            case 'HAPOTC':
            case '5361064':
            case '5361077':
            case '5361078':
                $Offer->categoryId = '000000'.'2';
                break;


            case '2300176':
            case '4679722':
            case '4679721':
            case '4679719':
                $Offer->categoryId = '000000'.'4';
                break;


            case 'B3132':
            case '1761527':
            case '25785587':
                $Offer->categoryId = '000000'.'5';
                break;



            case 'FRH52':
                $Offer->name = 'кукла БРИ БАННИ ENCHANTIMALS 31 СМ';
                $Offer->categoryId = '000000'.'1';
                break;
            case '52221':
                $Offer->name = 'кукла HAIRDORABLES JOJO LOVES ОГРАНИЧЕННЫЙ ВЫПУСК 1 СЕРИЯ';
                $Offer->categoryId = '000000'.'1';
                break;
            case 'GKT97':
                    $Offer->name = 'кукла ГАРРИ ПОТТЕР С ПАЛОЧКОЙ И ЗОЛОТЫМ ЯЙЦОМ';
                    $Offer->categoryId = '000000'.'1';
                break;
            case 'NN7365':
                $Offer->name = 'кукла ГАРРИ ПОТТЕР И СВЯТОЧНЫЙ БАЛ';
                $Offer->categoryId = '000000'.'1';
                break;
            case 'GFG13':
                $Offer->name = 'кукла ДОББИ СЕРИЯ ГАРРИ ПОТТЕР';
                $Offer->categoryId = '000000'.'1';
                break;


            case '573654':
                    $Offer->name = 'кукла POOPSIE SURPRISE ПУПСИ ЕДИНОРОЖКА Q.T. SUZY SUNSHINE';
                    $Offer->categoryId = '000000'.'1';
                break;
            case '573685':
                $Offer->name = 'кукла POOPSIE SURPRISE ПУПСИ ЕДИНОРОЖКА Q.T. - FIFI FRAZZLED';
                $Offer->categoryId = '000000'.'1';
                break;
            case '562658':
                $Offer->name = 'игрушка POOPSIE SURPRISE ПУПСИ СЮРПРИЗ - ЛАМА (БЕЛАЯ ИЛИ РОЗОВАЯ)';
                $Offer->categoryId = '000000'.'1';
                break;
            case '573661':
                $Offer->name = 'кукла POOPSIE SURPRISE ПУПСИ ЕДИНОРОЖКА Q.T. JENNA JITTERS';
                $Offer->categoryId = '000000'.'1';
                break;
            case 'GGV74':
                $Offer->name = 'кукла WILD HEARTS CREW ДЖЕЙСИ МЭСТЕРЗ';
                $Offer->categoryId = '000000'.'1';
                break;
            case 'GGV73':
                $Offer->name = 'кукла WILD HEARTS CREW ЧАРЛИ ЛЕЙК';
                $Offer->categoryId = '000000'.'1';
                break;
            case 'GGV76':
                $Offer->name = 'кукла WILD HEARTS CREW КЕННА РОЗУЭЛЛ';
                $Offer->categoryId = '000000'.'1';
                break;
            case 'FJD97':
                $Offer->name = 'кукла BARBIE ПЛЯЖ ГОЛУБОЙ КУПАЛЬНИК';
                $Offer->categoryId = '000000'.'1';
                break;
            case 'DWK00':
                $Offer->name = 'кукла BARBIE ПЛЯЖ РОЗОВЫЙ КУПАЛЬНИК';
                $Offer->categoryId = '000000'.'1';
                break;
            case '99180':
                $Offer->name = 'кукла CRYBABIES ПЛАЧУЩИЙ МЛАДЕНЕЦ DREAMY ЕДИНОРОГ';
                $Offer->categoryId = '000000'.'1';
                break;
            case '571162':
                $Offer->name = 'кукла ЕДИНОРОГ ТАНЦУЮЩИЙ POOPSIE SURPRISE ПУПСИ СЮРПРИЗ';
                $Offer->categoryId = '000000'.'1';
                break;

            case 'MX006':
                $Offer->name = 'РАСЧЕСКА для куклы МОНСТР ХАЙ';
                $Offer->categoryId = '000000'.'0';
                break;

            case 'GGY35':
                $Offer->name = 'НАБОР одежды и аксесуаров для куклы WILD HEARTS CREW РЭЛЛИ РЭДМО';
                $Offer->categoryId = '000000'.'7';
                break;
            case 'GGY36':
                $Offer->name = 'НАБОР одежды и аксесуаров для кукол WILD HEARTS CREW KOOL THING FASHIONS';
                $Offer->categoryId = '000000'.'7';
                break;
            case 'GGY27':
                $Offer->name = 'НАБОР одежды и аксесуаров для кукол WILD HEARTS CREW КЛАССНАЯ ПОЕЗДКА';
                $Offer->categoryId = '000000'.'7';
                break;
            case 'GGY29':
                $Offer->name = 'НАБОР одежды и аксесуаров для кукол WILD HEARTS CREW ЧАРЛИ ЛЕЙК';
                $Offer->categoryId = '000000'.'7';
                break;
            case 'GGY26':
                $Offer->name = 'НАБОР одежды и аксесуаров для кукол WILD HEARTS CREW КОРИ КРУЗ';
                $Offer->categoryId = '000000'.'7';
                break;

            case '20925':
                $Offer->name = 'детская ШКАТУЛКА ДЛЯ УКРАШЕНИЙ ХОЛОДНОЕ СЕРДЦЕ (ЗВУК)';
                $Offer->categoryId = '000000'.'3';
                break;
            case 'S18200':
                $Offer->name = 'детская ВОЛШЕБНАЯ ШКАТУЛКА С СЕКРЕТАМИ FUNLOCKETS';
                $Offer->categoryId = '000000'.'3';
                break;

            case 'RU2143':
                $Offer->name = 'игрушка ВОЛШЕБНАЯ ПАЛОЧКА ГАРРИ ПОТТЕРА';
                $Offer->categoryId = '000000'.'6';
                break;
            case '73210':
                $Offer->name = 'игрушка ВОЛШЕБНАЯ ПАЛОЧКА ГЕРМИОНЫ СЕРИЯ "ГАРРИ ПОТТЕР"';
                $Offer->categoryId = '000000'.'6';
                break;
        }
    }

    public static function addYMLCategories($Categories)
    {
        $newCats = [
            0 => 'Аксессуары для кукол',
            1 => 'куклы и пупсы',
            2 => 'Рюкзаки и ранцы для школы',
            3 => 'Играем в салон красоты',
            4 => 'Блокноты',
            5 => 'Аксессуары для детей',
            6 => 'Сюжетно-ролевые игры и игрушки',
            7 => 'Одежда для кукол',
        ];

        foreach($newCats as $key => $newCat)
        {
            $Category = $Categories->addChild('category', $newCat);
            $Category['id'] = '000000'.$key;
            $Category['parentId'] = '5472628';
        }
    }



    public static function saveProductsPrice()
    {
        dd('It does not need?');

        $pricesDate = Carbon::now();
        $insalesProducts = (new InsalesApi())->getProducts();

        foreach($insalesProducts as $InsalesProduct)
        {
            if($sku = Products::getInsalesProductParameter($InsalesProduct, 'sku'))
                $Product = Products::getProductBy('sku', $sku);

            $InsalesProductsPrice = new InsalesProductsPrice;
            $InsalesProductsPrice->prices_date = $pricesDate;
            if(isset($Product)) $InsalesProductsPrice->product_id = $Product->id;
            $InsalesProductsPrice->insales_product_id = $InsalesProduct->id;
            $InsalesProductsPrice->insales_variant_id = $InsalesProduct->variants[0]->id;
            $InsalesProductsPrice->sku = Products::getInsalesProductParameter($InsalesProduct, 'sku');
            $InsalesProductsPrice->price = Products::getInsalesProductParameter($InsalesProduct, 'price')??NULL;
            $InsalesProductsPrice->old_price = Products::getInsalesProductParameter($InsalesProduct, 'old_price')??NULL;

            $InsalesProductsPrice->save();
        }

        return 'ok';
    }

    public static function updateProductsPrice($products = [], $upPrice = 200)
    {
        dd('Does it need to change to update from product temp_price?');


        $insalesProductsPrices = InsalesProductsPrice::where([
            ['prices_date', '2021-11-10 10:14:48'],
        ])->get();

        $variants = [];
        foreach($insalesProductsPrices as $insalesProductsPrice)
        {
            $Variant = new \stdClass();
            $Variant->id = $insalesProductsPrice->insales_variant_id;
            $Variant->price = $insalesProductsPrice->price + $upPrice;
            if($insalesProductsPrice->old_price)
                $Variant->old_price = $insalesProductsPrice->old_price + $upPrice;
            $variants[] = $Variant;
        }


        $variantsChunks = array_chunk($variants, 100);

        foreach($variantsChunks as $variantsChunk)
        {
            $res = (new InsalesAPI())->makeRequest(
                'PUT',
                '/admin/products/variants_group_update.json',
                [
                    'variants' => $variantsChunk
                ]
            );
            dump($res);
        }

        return true;
    }

    public static function updateVariantTest()
    {
        $res = (new InsalesAPI())->makeRequest(
            'PUT',
            '/admin/products/variants_group_update.json',
            [
                'variants' => [
                    (object) [
                        'id' => 471607337,
                        'price' => 0,
                        'old_price' => null
                    ]
                ]
            ]
        );

        dd($res);
    }

    public static function updateAllVariantsPricesAndStocks()
    {
        $InsalesAPI = (new InsalesApi());
        $insalesProducts = $InsalesAPI->getProducts();
        $variantsToUpdate = []; // only price and old_price

        foreach($insalesProducts as $InsalesProduct)
        {
            foreach($InsalesProduct->variants as $InsalesProductVariant)
            {
                $Variant = new \stdClass();
                $Variant->id = $InsalesProductVariant->id;
                $Variant->quantity = 0;

                if($Product = Products::getProductBy('sku', $InsalesProductVariant->sku))
                {
                    $Variant->quantity = $Product->shopAmounts($InsalesAPI->shopId)->amounts->balance??0;
                    $Variant->price = $Product->temp_price?Price::recalculatePriceByUnloadingOption($Product->temp_price, $InsalesAPI->shopId):0;
                    $Variant->old_price = $Product->temp_old_price?Price::recalculatePriceByUnloadingOption($Product->temp_old_price, $InsalesAPI->shopId):null;
                }

                $variantsToUpdate[] = $Variant;
            }
        }

        $variantsToUpdateChunks = array_chunk($variantsToUpdate, 100);
        $countChunks = count($variantsToUpdateChunks);

        foreach($variantsToUpdateChunks as $keyChunk => $variantsToUpdateChunk)
        {
            $req = [
                'variants' => $variantsToUpdateChunk
            ];
            $res = (new InsalesAPI())->makeRequest(
                'PUT',
                '/admin/products/variants_group_update.json',
                $req
            );

            $error = false;
            if(!isset($res) or !$res)
            {
                $error = 'Has not answer';
            }else
            {
                foreach($res as $Res)
                {
                    if(!isset($Res->id) or !isset($Res->status) or ($Res->status !== 'ok'))
                    {
                        $error =  "Product error";
                        break;
                    }
                }
            }

            if($error)
            {
                dump($res, $error);
                $InsalesAPI->log('error', 'updateAllVariantsPrices', $error, $req, $res);
            }else
            {
                $InsalesAPI->log('info', 'updateAllVariantsPrices', '', $req, $res);
            }

            dump("Sent $keyChunk from $countChunks");
        }
    }

    public static function changeOrderTrackNumber($Order, $track): bool
    {
        return (new InsalesApi())->updateOrder($Order->system_order_id, [
            'order' => [
                'fields_values_attributes' => [(object) [
                    'handle' => 'track_number',
                    'value' => $track,
                ]]
            ]
        ]);
    }

    public static function getOrderFieldValue($SystemOrder, int $field_id)
    {
        if(isset($SystemOrder->fields_values) and !empty($SystemOrder->fields_values))
        {
            foreach($SystemOrder->fields_values as $FieldValue)
            {
                if($FieldValue->field_id === $field_id)
                {
                    return $FieldValue->value??false;
                }
            }
        }

        return false;
    }

    public static function getShopReqProductVariantTemplate($Product, $InsalesApi = false): \stdClass
    {
        $variant = (object) [
            'sku' => $Product->sku,
            'quantity' => $Product->shopAmounts($InsalesApi->shopId)->amounts->balance,
            'price' => $Product->priceByUnloadingOption($InsalesApi->shopId),
            'old_price' => $Product->oldPriceByUnloadingOption($InsalesApi->shopId),
            'barcode' => $Product->barcode,
            'weight' => $Product->BoxSizes->valueRealBoxWeightKg,
            'dimensions' => "{$Product->BoxSizes->valueRealBoxLength}x{$Product->BoxSizes->valueRealBoxWidth}x{$Product->BoxSizes->valueRealBoxHeight}",
        ];

        return $variant;

    }

    public static function getShopProductPropertiesAttributesTemplate($Product, $InsalesApi = false): array
    {
        if(!$InsalesApi) $InsalesApi = (new InsalesApi());

        $params = [];

        $params[] = (object) ['title' => 'Высота', 'value' => (string) $Product->height];
        $params[] = (object) ['title' => 'Категория', 'value' => $Product->category->name];
        $params[] = (object) ['title' => 'Материал', 'value' => $Product->material->name];
        $params[] = (object) ['title' => 'Персонажи', 'value' => $Product->character->name];
        $params[] = (object) ['title' => 'Гарантия качества', 'value' => $Product->quality_assurance?:'Оригинальный товар'];
        $params[] = (object) ['title' => 'Страна производитель', 'value' => $Product->producingCountry->name];
        $params[] = (object) ['title' => 'Производитель', 'value' => $Product->manufacturer->name];
        $params[] = (object) ['title' => 'Тип', 'value' => $Product->type->name];
        $params[] = (object) ['title' => 'Комплектация аксессуарами', 'value' => $Product->accessories?:'Да'];
        $params[] = (object) ['title' => 'Особенности', 'value' => $Product->peculiarities?:'Нет'];

        return $params;
    }
    public static function getShopReqProductTemplate($Product, $InsalesApi = false): \stdClass
    {
        if(!$InsalesApi) $InsalesApi = (new InsalesApi());

        $defaultCollectionId = 21000187; // new products
        if($Product->group->insales_collection_id)
            $defaultCollectionId = explode(',', $Product->group->insales_collection_id)[0];

        $reqProduct = (object) [
            'category_id' => 4823910, // default is Store
            'title' => $Product->name_ru,

            'dimensions' => "{$Product->BoxSizes->valueRealBoxLength}x{$Product->BoxSizes->valueRealBoxWidth}x{$Product->BoxSizes->valueRealBoxHeight}",
            'short_description' => $Product->temp_short_description,
            'canonical_url_collection_id' => $defaultCollectionId,

            'properties_attributes' => self::getShopProductPropertiesAttributesTemplate($Product, $InsalesApi),
        ];

        return $reqProduct;
    }

    public static function productsMatches()
    {
        $InsalesApi = (new InsalesApi());
        $start = microtime(true);
        $insalesProducts = $InsalesApi->getProducts();

        $updated = 0;
        $keyCode = uniqid();

        if(count($insalesProducts) > 0)
        {
            foreach($insalesProducts as $key => $InsalesProduct)
            {
                $sku = $InsalesProduct->variants[0]->sku??false;

                if($sku and $Product = Products::getProductBy('sku', $sku))
                {
                    $TypeShopProduct = TypeShopProduct::firstOrNew([
                        'type_shop_id' => $InsalesApi->shopId,
                        'product_id' => $Product->id,
                    ]);
                    $TypeShopProduct->shop_product_id = $InsalesProduct->id;
                    $TypeShopProduct->key_code = $keyCode;
                    $TypeShopProduct->save();
                    $updated++;
                }else{
                    dump('error', 'productsMatches', "Unknown product sku $sku");
                }

                print_r($key.' of '.count($insalesProducts)."\r");
            }

            TypeShopProduct::where([
                ['type_shop_id', $InsalesApi->shopId],
            ])->where(function($q) use ($keyCode)
            {
                $q
                    ->where('key_code', '!=', $keyCode)
                    ->orWhereNull('key_code');

            })->delete();
        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        dump('info', 'productsMatches', "Updated: $updated. $ExecutionTime");
    }

    public static function uploadProducts($productsListToImport = [])
    {
        $InsalesApi = (new InsalesApi());

        if(!$productsListToImport)
        {
            $skus = [];
            $insalesProducts = $InsalesApi->getProducts();
            foreach($insalesProducts as $key => $InsalesProduct)
            {
                if($sku = $InsalesProduct->variants[0]->sku ?? false)
                    $skus[] = $sku;
            }

            $productsListToImport = Product::where([
                ['state', '>', '-1'],
                ['temp_price', '>', 0]
            ])
                ->whereNotIn('sku', $skus)
                ->inRandomOrder()
                ->get();

            /*
            $productsListToImport = Product::where([
                ['state', '>', '-1'],
                ['temp_price', '>', 0]
            ])->whereDoesntHave('typeShopProducts', function ($q) use ($InsalesApi)
            {
                $q->where('type_shop_id', $InsalesApi->shopId);
            })
                ->inRandomOrder()
                ->get();
            */
        }

        if(count($productsListToImport) > 0)
        {
            dump('To upload '. count($productsListToImport));

            foreach($productsListToImport as $Product)
            {
                $reqProduct = self::getShopReqProductTemplate($Product, $InsalesApi);
                $reqProduct->variants_attributes = [
                    self::getShopReqProductVariantTemplate($Product, $InsalesApi)
                ];

                if($InsalesProduct = $InsalesApi->createProduct($reqProduct))
                {
                    $TypeShopProduct = TypeShopProduct::firstOrNew([
                        'type_shop_id' => $InsalesApi->shopId,
                        'product_id' => $Product->id,
                    ]);
                    $TypeShopProduct->shop_product_id = $InsalesProduct->id;
                    $TypeShopProduct->save();

                    self::updateCollections($Product, $InsalesApi, $InsalesProduct->id);
                    self::updateImages($Product, $InsalesApi, $InsalesProduct->id);
                }
            }
        }
    }

    public static function updateCollections($Product, $InsalesApi = false, $shopProductId = false)
    {
        if(!$InsalesApi) $InsalesApi = (new InsalesApi());
        if(!$shopProductId) $shopProductId = $Product->shopProductId($InsalesApi->shopId);

        if($Product->group->insales_collection_id)
        {
            $ids = explode(',', $Product->group->insales_collection_id);
            foreach($ids as $collectionId)
            {
                $InsalesApi->addProductCollection($shopProductId, (int) trim($collectionId));
            }
        }else
        {
            $InsalesApi->addProductCollection($shopProductId, 21000187);
        }
    }

    public static function updateProduct($Product, $InsalesApi = false, $shopProductId = false)
    {
        if(!$InsalesApi) $InsalesApi = (new InsalesApi());
        if(!$shopProductId) $shopProductId = $Product->shopProductId($InsalesApi->shopId);

        $reqProduct = self::getShopReqProductTemplate($Product, $InsalesApi);
        $InsalesApi->updateProduct($shopProductId, $reqProduct);
    }

    public static function updateVariant($Product, $InsalesApi = false, $shopProductId = false)
    {
        if(!$InsalesApi) $InsalesApi = (new InsalesApi());
        if(!$shopProductId) $shopProductId = $Product->shopProductId($InsalesApi->shopId);

        $InsalesVariant = $InsalesApi->getFirstVariant($shopProductId);

        $NewVariant = self::getShopReqProductVariantTemplate($Product, $InsalesApi);

        $InsalesApi->updateVariant(
            $shopProductId,
            $InsalesVariant->id,
            $NewVariant
        );
    }

    public static function checkImagesToUpdate($shopProductImages, $images): bool
    {
        if(count($shopProductImages) !== count($images)) return true;

        foreach($shopProductImages as $key => $ShopProductImage)
        {
            $externalId = (int) ($ShopProductImage->external_id??false);
            if(
                !isset($images[$key])
                or ($externalId !== $images[$key]->id)
            )
            {
                return true;
            }
        }

        return false;
    }

    public static function deleteShopProductImages($shopProductImages, $InsalesApi = false)
    {
        if(!$InsalesApi) $InsalesApi = (new InsalesApi());

        foreach($shopProductImages as $ShopProductImage)
        {
            $InsalesApi->deleteProductImages($ShopProductImage->product_id, $ShopProductImage->id);
        }
    }

    public static function updateImages($Product, $InsalesApi = false, $shopProductId = false)
    {
        if(!$InsalesApi) $InsalesApi = (new InsalesApi());
        if(!$shopProductId) $shopProductId = $Product->shopProductId($InsalesApi->shopId);

        $images = Products::getShopImages($Product, $InsalesApi->shopId);
        $shopProductImages = $InsalesApi->getProductImages($shopProductId);

        if(self::checkImagesToUpdate($shopProductImages, $images)) // checking images to update
        {
            self::deleteShopProductImages($shopProductImages, $InsalesApi);

            foreach($images as $Image)
            {
                $InsalesApi->createImageFromSrc($shopProductId, $Image);
            }
        }
    }

    public static function updateProducts($products = false)
    {
        $InsalesApi = (new InsalesApi());

        if(!$products)
        {
            $products = Products::getRecentlyUpdatedProduct($InsalesApi->shopId);
        }

        $countProducts = count($products);
        $updated = 0;
        foreach($products as $key => $Product)
        {
            if($shopProductId = $Product->shopProductId($InsalesApi->shopId))
            {
                self::updateProduct($Product, $InsalesApi, $shopProductId);
                self::updateVariant($Product, $InsalesApi, $shopProductId);
                self::updateImages($Product, $InsalesApi, $shopProductId);
                self::updateCollections($Product, $InsalesApi, $shopProductId);
                $updated++;
            }

            dump("$key / $countProducts $Product->sku");
        }

        dump("Updated $updated");
    }


    public static function removeDoubleProducts()
    {
        $InsalesApi = (new InsalesApi());
        $insalesProducts = $InsalesApi->getProducts();
        $skus = [];
        $toDelIds = [];
        $total = count($insalesProducts);

        foreach($insalesProducts as $key => $InsalesProduct)
        {
            dump("$key / $total");
            if($sku = $InsalesProduct->variants[0]->sku ?? false)
            {
                if(in_array($sku, $skus))
                {
                    $toDelIds[] = $InsalesProduct->id;
                }else
                {
                    $skus[] = $sku;
                }
            }
        }

        $total2 = count($toDelIds);
        dump("count to del $total2");

        foreach($toDelIds as $key => $id)
        {
            dump("$key / $total2");
            $InsalesApi->deleteProduct($id);
        }


    }
}


