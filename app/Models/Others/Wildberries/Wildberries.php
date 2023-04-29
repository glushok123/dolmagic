<?php

namespace App\Models\Others\Wildberries;

use App\Console\Api\Wildberries\WildberriesApi;
use App\Console\Api\Wildberries\WildberriesApi2;
use App\Console\Api\Wildberries\WildberriesStatApi;
use App\Console\Api\Wildberries\WildberriesStatApi2;
use App\Eloquent\Order\Order;
use App\Eloquent\Order\OrdersInfo;
use App\Eloquent\Order\OrdersTypeShop;
use App\Eloquent\Other\WB\WbStocksZero;
use App\Eloquent\Other\WB\WbUnknownProduct;
use App\Eloquent\Products\Product;
use App\Eloquent\Products\ProductsTempDiscountFile;
use App\Eloquent\Products\ProductsTempDiscountFileValue;
use App\Eloquent\Products\TypeShopProduct;
use App\Eloquent\Sales\Sale;
use App\Eloquent\Sales\SalesProduct;
use App\Eloquent\Shops\ShopProductsSize;
use App\Eloquent\Shops\ShopProductsUnloadingError;
use App\Eloquent\Warehouse\TypeShopWarehouse;
use App\Models\Model;
use App\Models\Orders;
use App\Models\Prices\Price;
use App\Models\Products;
use App\Models\Shops\ShopProducts;
use App\Models\Shops\Shops;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\Others\Excel\SimpleXLSX;


class Wildberries extends Model
{

    public static function getDiscountPercent($ActualPrice): int
    {
        // (3-95%)

        $discountPercent = 0;
        $price = $ActualPrice->price;
        $oldPrice = $ActualPrice->old_price;

        if($price and $oldPrice and ($oldPrice > $price))
        {
            $discountPercent = 100 - (($price / $oldPrice) * 100);
            $discountPercent = (int) floor($discountPercent);

            if(($discountPercent < 3) or ($discountPercent > 95))
                $discountPercent = 0;
        }

        return $discountPercent;
    }

    public static function getActionPrice($Product, $shopId)
    {
        $postfixes = ShopProducts::getShopPostfixes($shopId, true);

        if($Value = ProductsTempDiscountFileValue
            ::where(function($q) use ($Product, $postfixes)
            {
                $q->where('sku', $Product->sku);
                if($postfixes)
                {
                    foreach($postfixes as $postfix)
                    {
                        $q->orWhere('sku', $Product->sku.'-'.$postfix);
                    }
                }
            })
            ->where('active', 1)
            ->whereHas('file', function($q) use ($shopId)
            {
                $q
                    ->where('shop_id', $shopId)
                    ->where('active', 1)
                    ->where('period_from', '<=', Carbon::now()->setTimezone('Europe/Moscow'))
                    ->where('period_to', '>=', Carbon::now()->setTimezone('Europe/Moscow'));
            })
            ->orderBy('new_discount', 'DESC')
            ->first())
        {
            return $Value;
        }else
        {
            return false;
        }
    }

    public static function getPriceWithAction($Product, $shopId = 177)
    {
        $res = new \stdClass();
        $res->price = 0;
        $res->discount = 0;

        if(!$Product) return $res;

        // if in action
        if($ActionPrice = self::getActionPrice($Product, $shopId))
        {
            $res->price = (int) $ActionPrice->new_price;
            $res->discount = (int) $ActionPrice->new_discount;
        }else
        {
            return self::getPrice($Product, $shopId);
        }

        return $res;
    }

    public static function getPrice($Product, $shopId)
    {
        $res = new \stdClass();
        $res->price = 0;
        $res->discount = 0;

        if($Product and $Product->temp_price)
        {
            $ActualPrice = $Product->actualPrice($shopId);

            if($ActualPrice->old_price and ($ActualPrice->old_price > $ActualPrice->price))
            {
                $res->price = $ActualPrice->old_price;
                $res->discount = self::getDiscountPercent($ActualPrice);
            }else
            {
                $res->price = $ActualPrice->price;
            }
        }

        return $res;
    }

    public static function getWbPriceFromList($nmId, $wbPrices)
    {
        foreach($wbPrices as $WBPrice)
        {
            if($WBPrice->nmId === $nmId)
            {
                return $WBPrice;
            }
        }
        return false;
    }
    public static function updatePriceUp30Percent(&$Price, $WildberriesProduct, $wbPrices)
    {
        $maxUpPercent = 19; // since 2022-11-10
        if($WbPrice = self::getWbPriceFromList($WildberriesProduct->nmID, $wbPrices))
        {
            $upPrice = $Price->price - $WbPrice->price;
            if(($WbPrice->price > 0) and ($upPrice > 0))
            {
                $upPricePercent = floor($upPrice / $WbPrice->price * 100);
                if($upPricePercent > $maxUpPercent)
                {
                    $Price->price = (int) ($WbPrice->price + $WbPrice->price * $maxUpPercent / 100);
                    $Price->discount = 0;
                }
            }
        }
    }

    public static function updatePrices($shopId, $products = false)
    {
        $WildberriesApi = self::getApi($shopId);
        $wildberriesProducts = $WildberriesApi->contentV1CardsCursorList();
        $wbPrices = $WildberriesApi->getInfo();

        if($products)
            self::filterByProducts($wildberriesProducts, $products);

        $countProducts = count($wildberriesProducts);
        $pricesToUpdate = [];
        $discounts = new \stdClass();
        $discounts->toUpdate = [];
        $discounts->toRemove = [];

        foreach($wildberriesProducts as $key => $WildberriesProduct)
        {
            dump("$key of $countProducts $WildberriesProduct->vendorCode");

            //$sku = Products::getProductSkuWithPostfix($WildberriesProduct->supplierVendorCode);
            //$Product = Product::where('sku', $sku)->first();

            if(!$Product = Products::getProductWithPostfix($WildberriesProduct->vendorCode))
            {
                dump("Product not found getProductWithPostfix");
                continue;
            }

            $Price = self::getPriceWithAction($Product, $shopId);

            // if price up more than 30 percent
            //dump($Price);
            self::updatePriceUp30Percent($Price, $WildberriesProduct, $wbPrices);
            //dump($Price);

            if($Price->discount)
            {
                $discounts->toUpdate[] = (object) [
                    'nm' => $WildberriesProduct->nmID,
                    'discount' => $Price->discount,
                ];
            }else
            {
                $discounts->toRemove[] = $WildberriesProduct->nmID;
            }

            $PriceToUpdate = new \stdClass();
            $PriceToUpdate->nmId = $WildberriesProduct->nmID;
            $PriceToUpdate->price = $Price->price;
            $pricesToUpdate[] = $PriceToUpdate;
        }

        dump('Total '.count($pricesToUpdate).' to update.. Trying..');
        $WildberriesApi->updatePrice($pricesToUpdate);

        dump('Total '.count($discounts->toUpdate).' to updateDiscounts.. Trying..');
        $WildberriesApi->updateDiscounts($discounts->toUpdate);

        dump('Total '.count($discounts->toRemove).' to revokeDiscounts.. Trying..');
        $WildberriesApi->revokeDiscounts($discounts->toRemove);

        dump('Success updated');
    }

    public static function filterByProducts(&$wildberriesProducts, &$products)
    {
        foreach($wildberriesProducts as $key => $WildberriesProduct)
        {
            $found = false;

            foreach($products as $Product)
            {
                if($WProduct = Products::getProductWithPostfix($WildberriesProduct->vendorCode))
                {
                    if($WProduct->id === $Product->id)
                    {
                        $found = true;
                        break;
                    }
                }
            }

            if(!$found)
                unset($wildberriesProducts[$key]);
        }

        $wildberriesProducts = array_values($wildberriesProducts);
    }

    public static function checkPriceAndDiscount($Product, $nmId, $wbPrices, $shopId)
    {
        $res = new \stdClass();
        $res->equal = false;
        $res->crmPrice = 0;
        $res->crmDiscount = 0;
        $res->wbPrice = 0;
        $res->wbDiscount = 0;

        foreach($wbPrices as $WBPrice)
        {
            if($WBPrice->nmId === $nmId)
            {
                $Price = self::getPriceWithAction($Product, $shopId);


                $res->crmPrice = $Price->price;
                $res->crmDiscount = $Price->discount;
                $res->wbPrice = $WBPrice->price;
                $res->wbDiscount = $WBPrice->discount;

                if(
                    ($Price->price === $WBPrice->price)
                    and ($Price->discount === $WBPrice->discount)
                )
                {
                    $res->equal = true;
                    return $res;
                }
                break;
            }
        }

        return $res;
    }

    public static function updateStocks($shopId, $products = false)
    {
        $countBalanced = 0;
        $tempZeros = [];
        //$tempZeros = ['87436','E7413','E5599','GTH02','720959','NP381','3313-C','50001','P50005','50002','GBK11','50356','GJG37','GWF11','564720','50601','P50004','E6710','GHX42','PT-00828-1','GBK12','GWD85','GHX84','GHX44','50365','GHX41','827345','20686','B9204','920077','575702','F1115','F1057','GJM72','GHX86','6061837','GHX85','B9164','659822','GHX72','FYM51','572657','827321','9008715','824-344','CBD13','F1411','358599','GYJ05','358574','332599','50330','332544','5079598','2899736','563877','457898','570349','P50003','F1825','F1955','F3391','GYN61','F4562','F4993','GRC88','F1558','GMT46','GWF10','GXF92','GXF93','GHX43','101255','F1412','555742','572671','564898','551508','577904','527510','524717','522577','50060','300176','23906','13704','577928','F0896','93812','F0796','F0594','E9999','E8405','E0237','DVX55','DSN0201-007','DSN0201-006','DSN0201-005','109358416','BMB99','91665','587545','88184','830604','823903','80706','711907','696341','665244','635555','6064289','6061836','6061835','5987541','FJF46','FJF39','996644','C1756','65331','77618','B0987','42570','236699','30462P','F0082','E9473','GVB33','GVB31','810755','E2312','699365','18943','5258966','42568','213061','F1689','36870','46210','44671','87252','210441','E3145','F5555','GXL14','GJK10','GJK09','HBV29','HGP65','720953','HFJ70','E4992','90094','918179','BDD90','Y0404','44672','400914','211531','B076F','MS046','MS051','FNH25','F4564','575696','576600','BHY15'];
        $keyCode = uniqid();

        $WildberriesApi = self::getApi($shopId);
        $wbPrices = $WildberriesApi->getInfo();
        $wildberriesProducts = $WildberriesApi->contentV1CardsCursorList();

        if($products)
            self::filterByProducts($wildberriesProducts, $products);

        $countProducts = count($wildberriesProducts);

        $stocksToUpdate = [];
        $i = 0;
        foreach($wildberriesProducts as $key => $WildberriesProduct)
        {
            $i++;
            dump("$i of $countProducts $WildberriesProduct->vendorCode");

            $stock = 0;
            if($Product = Products::getProductWithPostfix($WildberriesProduct->vendorCode, $shopId))
            {
                $Stop = Products::getSystemsProductsStopResult($Product, $shopId);
                $stock = $Stop->stock?0:$Product->shopAmounts($shopId)->amounts->balance;
                $stock = $Product->yandex_not_upload?0:$stock;

                if($stock > 0) // check prices and discounts
                {
                    $PriceCheck = self::checkPriceAndDiscount($Product, $WildberriesProduct->nmID, $wbPrices, $shopId);
                    if(!$PriceCheck->equal)
                    {
                        $WbStocksZero = new WbStocksZero;
                        $WbStocksZero->shop_id = $shopId;
                        $WbStocksZero->product_id = $Product->id;
                        $WbStocksZero->quantity = $stock;
                        $WbStocksZero->crm_price = $PriceCheck->crmPrice;
                        $WbStocksZero->crm_discount = $PriceCheck->crmDiscount;
                        $WbStocksZero->wb_price = $PriceCheck->wbPrice;
                        $WbStocksZero->wb_discount = $PriceCheck->wbDiscount;
                        $WbStocksZero->key_code = $keyCode;
                        $WbStocksZero->save();

                        $stock = 0;
                    }
                }

                if(($shopId === 177) and in_array($Product->sku, $tempZeros)) // temp Ira from 2022-06-27 21:00
                {
                    $stock = 0;
                }
            }

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
                                $Stock = new \stdClass();
                                $Stock->sku = $barcode;
                                $Stock->amount = $stock;

                                $stocksToUpdate[] = $Stock;
                            }
                        }
                    }
                }

                if($stock > 0) $countBalanced++;
            }
        }

        dump("Count balanced = $countBalanced");

        dump('Total '.count($stocksToUpdate).' to update.. Trying..');
        $WildberriesApi->updateStockV3($stocksToUpdate);
        dump('Success updated');
    }

    public static function getZeroStocks($shopId)
    {
        $WildberriesApi = self::getApi($shopId);
        $wbStocks = $WildberriesApi->getAllStocksV3();

        $inStockSkus = [];
        $unknownStockBarcodes = [];

        foreach($wbStocks as $WBStock)
        {
            if($WBStock->amount > 0)
            {

                if($Product = Products::getProductByBarcode($WBStock->sku))
                {
                    $inStockSkus[] = $Product->sku;
                }else
                {
                    $unknownStockBarcodes[] = $WBStock->sku;
                }
            }
        }

        $products = Product::whereNotIn('sku', $inStockSkus)
            ->whereHas('amounts', function ($amounts) use ($shopId)
            {
                $amounts
                    ->where('available', '>', 0)
                    ->whereIn('warehouse_id', Shops::getShopWarehouses($shopId)->pluck('id')->toArray());
            })
            ->where('archive', 0)
            ->where('temp_price', '>', 0)
            ->where('yandex_not_upload', 0)
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
        return $products;
    }

    /*
    public static function uploadProductsWithPostfix($products = false)
    {
        if(!$products)
            $products = self::getZeroStocks();

        self::uploadProducts($products, true);
    }
    */

    public static function getApi($shopId)
    {
        if($Shop = OrdersTypeShop::where('id', $shopId)->first())
            $shopId = $Shop->main_shop_id?:$shopId;

        $Api = false;
        switch($shopId)
        {
            case 177:
                $Api = new WildberriesApi();
                break;
            case 179:
                $Api = new WildberriesApi2();
                break;
        }

        return $Api;
    }

    public static function getStatApi($shopId)
    {
        if($Shop = OrdersTypeShop::where('id', $shopId)->first())
            $shopId = $Shop->main_shop_id?:$shopId;

        $Api = false;
        switch($shopId)
        {
            case 177:
                $Api = new WildberriesStatApi();
                break;
            case 179:
                $Api = new WildberriesStatApi2();
                break;
        }

        return $Api;
    }



    /*
    public static function updateAllProducts($shopId)
    {
        $WildberriesApi = self::getApi($shopId);
        $cardList = $WildberriesApi->cardList();
        $countProducts = count($cardList);
        $updated = 0;
        $error = 0;

        foreach($cardList as $key => $WildberriesProduct)
        {
            if(!$Product = Products::getProductWithPostfix($WildberriesProduct->supplierVendorCode)) continue;

            $num = $key+1;
            dump("Updating $num of $countProducts: $Product->sku");

            $card = self::getProductTemplate($Product, $WildberriesApi, false, $WildberriesProduct);

            if($WildberriesApi->cardUpdate($card))
            {
                $updated++;
            }else
            {
                $error++;
            }
        }

        dump("Updated $updated of $countProducts, errors: $error");
    }
    */

    public static function updateProducts($shopId, $products = false)
    {
        $WildberriesApi = self::getApi($shopId);

        if(!$products)
        {
            $products = Product::whereHas('typeShopProducts', function ($q) use ($WildberriesApi)
            {
                $q->where('type_shop_id', $WildberriesApi->shopId);
            })
                ->where('state', '>', -1)
                ->where('archive', 0)
                ->where('temp_price', '>', 0)
                ->where('yandex_not_upload', 0);

            if($WildberriesApi->shopId === 177) $products->whereNotNull('barcode1');
            if($WildberriesApi->shopId === 179) $products->whereNotNull('barcode2');

            $products = $products->get();
        }

        $countProducts = count($products);
        $updated = 0;
        $error = 0;
        $errorSkus = [];
        foreach($products as $key => $Product)
        {
            $num = $key+1;
            dump("Updating $num of $countProducts: $Product->sku");

            if(!$TypeShopProduct = $Product->typeShopProducts->where('type_shop_id', $WildberriesApi->shopId)->first())
            {
                dump("product $Product->sku hasn't TypeShopProduct");
                continue;
            }

            if(!$wbCard = $WildberriesApi->contentV1CardsFilter([$TypeShopProduct->uploaded_sku]))
            {
                dump("product $Product->sku hasn't wbCard");
                continue;
            }

            self::updateProductCard($Product, $wbCard, $shopId);
            self::setCardPhoto($wbCard->vendorCode, $Product, $WildberriesApi);

            if($WildberriesApi->contentV1CardsUpdate($wbCard))
            {
                $updated++;
            }else
            {
                $error++;
                $errorSkus[] = $Product->sku;
            }
        }

        dump("Updated $updated of $countProducts, errors: $error");
        dump("Errors skus:");
        dump($errorSkus);

        return empty($errorSkus);
    }

    public static function createCardAddin($Product)
    {
        return [
            (object) [
                'type' => 'Наименование',
                'params' => [
                    (object) [
                        'value' => ''
                    ]
                ]
            ],

            (object) [
                'type' => 'Бренд',
                'params' => [
                    (object) [
                        'value' => ''
                    ]
                ]
            ],
            (object) [
                'type' => 'Комплектация',
                'params' => [
                    (object) [
                        'value' => ''
                    ]
                ]
            ],

            (object) [
                'type' => 'Описание',
                'params' => [
                    (object) [
                        'value' => ''
                    ]
                ]
            ],

            (object) [
                'type' => 'Глубина упаковки',
                'params' => [
                    (object) [
                        'value' => ''
                    ]
                ]
            ],
        ];
    }

    public static function createCardNomenclatures($shopId, $Product)
    {
        $variant = new \stdClass();
        $variant->barcode = ShopProducts::getShopBarcode($shopId, $Product);

        $variant->addin = [
            (object) [
                'type' => 'Розничная цена',
                'params' => [
                    (object) [
                        'count' => 0,
                        'units' => 'рублей'
                    ]
                ]
            ],
        ];

        $nomenclature = (object)
        [
            'vendorCode' => $Product->sku,
            'variations' => [$variant],
        ];

        return [$nomenclature];
    }

    public static function clearCardPhotos(&$card)
    {
        if(isset($card->nomenclatures[0]->addin))
        {
            foreach($card->nomenclatures[0]->addin as $key => $Addin)
            {
                if($Addin->type === 'Фото')
                {
                    unset($card->nomenclatures[0]->addin[$key]);
                }
            }

            $card->nomenclatures[0]->addin = array_values($card->nomenclatures[0]->addin);
        }
    }

    public static function setCardPhoto(string $vendorCode, $Product, $WildberriesApi)
    {
        $images = Products::getShopImages($Product, $WildberriesApi->shopId);
        $data = [];
        foreach($images as $ProductImage)
        {
            $data[] = $ProductImage->ShortLink;
        }
        $WildberriesApi->contentV1MediaSave($vendorCode, $data);
    }

    public static function updateCardPhotos(&$card, $Product, $WildberriesApi)
    {
        self::clearCardPhotos($card);

        if(!isset($card->nomenclatures[0]->addin))
            $card->nomenclatures[0]->addin = [];

        $images = Products::getShopImages($Product, $WildberriesApi->shopId);
        $photos = [];
        foreach($images as $ProductImage)
        {
            $Photo = new \stdClass();
            $Photo->value = $ProductImage->HttpShortLink;
            $photos[] = $Photo;
        }

        $card->nomenclatures[0]->addin[] = (object)
        [
            'type' => 'Фото',
            'params' => $photos
        ];
    }


    public static function getCharacteristicValue($charName, $Product, $shopId)
    {
        switch($charName)
        {
            case 'Предмет': return $Product->caterory->wildberries??'Куклы';
            case 'Наименование': return mb_substr(Products::getName($Product, $shopId), 0, 40);
            case 'Бренд': return $Product->character->name;
            case 'Описание': return $Product->ClearDescription;
            case 'Комплектация': return 'полная';

            case 'Страна производства': return $Product->producingCountry->name;

            case 'Длина упаковки':
            case 'Глубина упаковки': return $Product->ShopBoxSizes($shopId)->box_length;
            case 'Ширина упаковки': return $Product->ShopBoxSizes($shopId)->box_width;
            case 'Высота упаковки': return $Product->ShopBoxSizes($shopId)->box_height;
            case 'Вес товара с упаковкой (г)': return $Product->ShopBoxSizes($shopId)->box_weight;


            case 'Тип куклы': return $Product->type->name;
            case 'Возрастные ограничения': return ['3+'];
            // these can be null
            case 'Высота куклы': return $Product->height?[$Product->height]:false;
            case 'Особенности куклы': return $Product->peculiarities?[$Product->peculiarities]:false;
            case 'Материал игрушки': return isset($Product->material->name)?[$Product->material->name]:false;
        }

        return false;
    }

    public static function updateProductCard($Product, &$wbCard, $shopId)
    {
        $defaultCharacteristics = self::getCharacteristicList();

        foreach($wbCard->characteristics as $key => $Char)
        {
            foreach($Char as $charName => $charValue)
            {
                if($charName === 'Бренд') continue; // IRA since 2022-11-10 17:38 skype

                if($value = self::getCharacteristicValue($charName, $Product, $shopId))
                    $wbCard->characteristics[$key] = (object)
                    [
                        $charName => $value
                    ];

                if(isset($defaultCharacteristics[$charName]))
                    $defaultCharacteristics[$charName] = true;
            }
        }

        self::addProductTemplateChars($wbCard->characteristics, $Product, $defaultCharacteristics, $shopId);
    }

    public static function getCharacteristicList(): array
    {
        return [
            'Предмет' => false,
            'Наименование' => false,
            'Бренд' => false,
            'Описание' => false,
            'Комплектация' => false,
            'Вес товара с упаковкой (г)' => false,
            'Страна производства' => false,
            'Длина упаковки' => false,
            'Глубина упаковки' => false,
            'Ширина упаковки' => false,
            'Высота упаковки' => false,
            'Тип куклы' => false,
            'Возрастные ограничения' => false,
        ];
    }

    public static function addProductTemplateChars(&$cardCharacteristics, $Product, $defaultCharacteristics, $shopId)
    {
        foreach($defaultCharacteristics as $charName => $filled)
        {
            if(!$filled and $value = self::getCharacteristicValue($charName, $Product, $shopId))
                $cardCharacteristics[] = (object)
                [
                    $charName => $value
                ];
        }
    }
    public static function getProductTemplate($Product, $shopId): \stdClass
    {
        $card = new \stdClass();
        $card->vendorCode = ShopProducts::getShopSku($Product->sku, $shopId);

        $defaultCharacteristics = self::getCharacteristicList();
        $card->characteristics = [];
        self::addProductTemplateChars($card->characteristics, $Product, $defaultCharacteristics, $shopId);

        $card->sizes = [
            [
                //'chrtID' => 0,
                //'techSize' => "0",
                //'wbSize' => "0",
                'price' => (int) Price::recalculatePriceByUnloadingOption($Product->temp_price, $shopId),
                'skus' =>
                    [
                        ShopProducts::getShopBarcode($shopId, $Product)
                    ]
            ]
        ];

        return $card;

        self::updateCardPhotos($card, $Product, $WildberriesApi);

        return $card;
    }

    public static function uploadProducts($shopId, $products = false, $postfix = false, $stopOnError = false)
    {
        $WildberriesApi = self::getApi($shopId);
        $keyCode = uniqid();

        if(!$products)
        {
            $products = Product::whereDoesntHave('typeShopProducts', function ($q) use ($WildberriesApi)
            {
                $q->where('type_shop_id', $WildberriesApi->shopId);
            })
                ->where('state', '>', -1)
                ->where('archive', 0)
                ->where('temp_price', '>', 0)
                ->where('yandex_not_upload', 0);

            if($WildberriesApi->shopId === 177) $products->whereNotNull('barcode1');
            if($WildberriesApi->shopId === 179) $products->whereNotNull('barcode2');

            $products = $products->get();
        }

        $created = 0;
        $errors = 0;
        $countToCreate = count($products);
        foreach($products as $key => $Product)
        {
            dump(($key + 1)." of ". $countToCreate);

            $card = self::getProductTemplate($Product, $WildberriesApi->shopId);

            if($error = $WildberriesApi->contentV1CardsUpload([$card]))
            {
                $ShopProductsUnloadingError = new ShopProductsUnloadingError;
                $ShopProductsUnloadingError->shop_id = $WildberriesApi->shopId;
                $ShopProductsUnloadingError->sku = $Product->sku;
                $ShopProductsUnloadingError->type = $error->errorText??'Неизвестно';
                $ShopProductsUnloadingError->info = $error->additionalErrors??'Неизвестно';
                $ShopProductsUnloadingError->key_code = $keyCode;
                $ShopProductsUnloadingError->save();

                if($stopOnError)
                {
                    dd($error);
                }

                $errors++;
            }else
            {
                $created++;
            }
        }

        dump("$created / $countToCreate created. Errors: $errors");
    }

    public static function getAllProducts($WildberriesApi = false) // ONLY FOR CHECK PRODUCTS -> can remove
    {
       // dd('didnt use');
        if(!$WildberriesApi) $WildberriesApi = new WildberriesApi;

        $products1 =  $WildberriesApi->cardList();
        $products2 = $WildberriesApi->cardList(true);

        dd($products2[0]);

        return array_merge($products1, $products2);

    }

    public static function productsMatches($shopId)
    {
        $WildberriesApi = self::getApi($shopId);

        $wildberriesProducts = $WildberriesApi->contentV1CardsCursorList();

        $updated = 0;
        $keyCode = uniqid();

        $countProducts = count($wildberriesProducts);

        foreach($wildberriesProducts as $key => $WildberriesProduct)
        {
            dump("$key of $countProducts");

            $sku = Products::getProductSkuWithPostfix($WildberriesProduct->vendorCode);
            if($Product = Products::getProductBy('sku', $sku))
            {
                $TypeShopProduct = TypeShopProduct::firstOrNew([
                    'type_shop_id' => $WildberriesApi->shopId,
                    'product_id' => $Product->id,
                ]);
                $TypeShopProduct->shop_product_id = $WildberriesProduct->nmID; // nmID
                $TypeShopProduct->key_code = $keyCode;
                $TypeShopProduct->uploaded_sku = $WildberriesProduct->vendorCode;
                $TypeShopProduct->save();
                $updated++;
            }else{
                $WildberriesApi->log('error', 'productsMatches', "Unknown product sku $WildberriesProduct->vendorCode");
                dump("Unknown product sku $WildberriesProduct->vendorCode");
            }
        }

        TypeShopProduct::where([
            ['type_shop_id', $WildberriesApi->shopId],
        ])->where(function($q) use ($keyCode)
        {
            $q
                ->where('key_code', '!=', $keyCode)
                ->orWhereNull('key_code');
        })->delete();
    }

    public static function saveOrdersShkId($shopId, $lasMonth = true)
    {
        $WildberriesStatApi = Wildberries::getStatApi($shopId);

        $orders = Order::whereHas('sale', function($q)
        {
            $q->whereNull('shk_id')->orWhere('shk_id', '');
        })
            ->where('type_shop_id', $WildberriesStatApi->shopId);


        if($lasMonth)
            $orders->whereHas('info', function($q)
            {
                $q->where('order_date_create', '>=', Carbon::now()->subMonths(3));
            });

        $orders = $orders
            ->get()
            ->sortBy('info.order_date_create');

        dump(count($orders));

        if(count($orders) > 0)
        {
            $dateFrom = Carbon::parse($orders[0]->info->order_date_create)->format('Y-m-d');
            $dateTo = Carbon::parse($orders[count($orders) - 1]->info->order_date_create)->format('Y-m-d');

            $reports = $WildberriesStatApi->getSupplierReportDetailByPeriod($dateFrom, $dateTo);

            $countReports = count($reports);
            foreach($reports as $key => $Report)
            {
                dump("$key / $countReports");

                $Order = Order::whereHas('sale', function($q) use ($Report)
                {
                    $q
                        ->where('rid', $Report->srid)
                        ->orWHere('srid', $Report->srid);
                })
                    ->where('type_shop_id', $WildberriesStatApi->shopId)
                    ->first();

                if($Order)
                {
                    if($OrderInfo = $Order->info)
                    {
                        $OrderInfo->shk_id = $Report->shk_id;
                        $OrderInfo->save();
                    }
                    if($Sale = $Order->sale)
                    {
                        $Sale->shk_id = $Report->shk_id;
                        $Sale->save();
                    }
                }
            }
        }

        dump('end');
    }

    public static function saveOrdersStickerId($shopId)
    {
        $WildberriesApi = Wildberries::getApi($shopId);
        $orders = Order::whereHas('sale', function($q)
        {
            $q->whereNull('sticker_id')->orWhere('sticker_id', '');
        })
            ->where('type_shop_id', $WildberriesApi->shopId)
            ->get();

        $systemOrdersIds = $orders->pluck('system_order_id')->toArray();

        dump(count($systemOrdersIds));

        foreach($systemOrdersIds as $key => $systemOrderId)
        {
            $systemOrdersIds[$key] = (int) $systemOrderId;
        }

        if($stickers = $WildberriesApi->getOrdersStickersV3($systemOrdersIds))
        {
            $countStickers = count($stickers);
            foreach($stickers as $key => $Sticker)
            {
                dump("$key / $countStickers");
                if($Sale = Sale::whereHas('order', function($q) use ($Sticker)
                {
                    $q->where('system_order_id', $Sticker->orderId);
                })->first())
                {
                    $Sale->sticker_id = ($Sticker->partA??'') . ($Sticker->partB??'');
                    $Sale->save();
                }
            }
        }else
        {
            dump('none stickers');
        }

        dump('end');
    }

    public static function getSOrder($SystemOrder)
    {

        $dateFrom = Carbon::parse($SystemOrder->dateCreated)->subDays(1)->format('Y-m-d\T00:00:00.000\Z');
        $sOrders = (new WildberriesStatApi())->getSupplierOrders($dateFrom);

        if(!$sOrders)
        {
            dump('sOrders not found');
            return false;
        }
        $FoundSOrder = false;

        foreach($sOrders as $SOrder)
        {
            if($SOrder->odid == $SystemOrder->rid)
            {
                $FoundSOrder = $SOrder;
                break;
            }
        }

        if($FoundSOrder)
        {
            dd($FoundSOrder);
            return $FoundSOrder;
        }else
        {
            dump('SOrder not found');
        }

        return false;
    }

    public static function getDiscountColumnsNamesKeys($xlsx, $sheetKey, $sheetName, $shopId)
    {
        $keys = new \stdClass();
        $keys->fields = new \stdClass();
        $keys->titleRowNumber = false;

        switch($shopId)
        {
            case 177:
            case 179:
            switch($sheetName)
            {
                case 'Отчёт по скидкам для акции': // services
                        $keys->fields->sku = new \stdClass();
                        $keys->fields->sku->key = 'Артикул'.PHP_EOL.'поставщика';

                        $keys->fields->plannedPrice = new \stdClass();
                        $keys->fields->plannedPrice->key = 'Плановая цена для акции';

                        $keys->fields->newPrice = new \stdClass();
                        //$keys->fields->newPrice->key = 'Загружаемая цена для участия в акции';
                        $keys->fields->newPrice->key = 'Текущая розничная цена';

                        $keys->fields->newDiscount = new \stdClass();
                        $keys->fields->newDiscount->key = 'Загружаемая скидка для участия в акции';
                    break;
            }
        }

        foreach($xlsx->rows($sheetKey) as $rowNumber => $row)
        {
            if(($rowNumber === 10) and empty($keys)) return false;

            foreach($row as $columnNumber => $column)
            {
                $tColumn = trim($column);
                if(empty($column)) continue; // skip empty column names

                foreach($keys->fields as $Field)
                {
                    if($tColumn === $Field->key)
                    {
                        $Field->columnNumber = $columnNumber;
                        $keys->titleRowNumber = $rowNumber;
                    }
                }
            }

            if($keys->titleRowNumber !== false)
                break; // title row found
        }

        return $keys;
    }

    public static function getSku($doubleSku)
    {
        return $doubleSku?(str_split($doubleSku, round(strlen($doubleSku) / 2))[0]):$doubleSku;
    }

    public static function parseDiscountSheet($xlsx, $sheetKey, $sheetName, $fileId, $shopId)
    {
        $columnKeys = self::getDiscountColumnsNamesKeys($xlsx, $sheetKey, $sheetName, $shopId);

        if($columnKeys)
        {
            foreach($xlsx->rows($sheetKey) as $rowNumber => $row)
            {
                if($rowNumber <= $columnKeys->titleRowNumber) continue; // rows before title

                $plannedPrice = (float) $row[$columnKeys->fields->plannedPrice->columnNumber];
                $newPrice = isset($columnKeys->fields->newPrice->columnNumber)?
                    ((float) ($row[$columnKeys->fields->newPrice->columnNumber]))
                    :0;

                $newDiscount = (float) $row[$columnKeys->fields->newDiscount->columnNumber];
                $sku = (string) $row[$columnKeys->fields->sku->columnNumber];
                //$sku = self::getSku($sku); //str_split($sku, round(strlen($sku) / 2))[0];
                $sku = Products::getProductSkuWithPostfix($sku);

                if($plannedPrice or $newPrice or $newDiscount or $sku)
                {
                    $ProductsTempDiscountFileValue = new ProductsTempDiscountFileValue;
                    $ProductsTempDiscountFileValue->shop_id = $shopId;
                    $ProductsTempDiscountFileValue->file_id = $fileId;
                    if($Product = Product::where('sku', $sku)->first())
                    {
                        $ProductsTempDiscountFileValue->product_id = $Product->id;
                    }
                    $ProductsTempDiscountFileValue->sku = $sku;
                    $ProductsTempDiscountFileValue->planned_price = $plannedPrice;
                    $ProductsTempDiscountFileValue->new_price = $newPrice;
                    $ProductsTempDiscountFileValue->new_discount = $newDiscount;
                    $ProductsTempDiscountFileValue->save();
                }else
                {
                    return true;
                }
            }
        }

        return false;
    }

    public static function parseDiscountSheets($xlsx, $fileId, $shopId)
    {
        $sheetNames = $xlsx->sheetNames();
        foreach($sheetNames as $sheetKey => $sheetName)
        {
            if(!self::parseDiscountSheet($xlsx, $sheetKey, $sheetName, $fileId, $shopId))
            {
                return false;
            }
        }

        return true; // ?
    }

    public static function saveActionsDiscountsFile($File, $shopId, $periodFrom, $periodTo, $title)
    {

        $fileId = self::saveActionsDiscountsFileToDisk($File, $shopId, $periodFrom, $periodTo, $title);
        $filePath = $File->getRealPath();

        if($xlsx = SimpleXLSX::parse($filePath))
        {
            return self::parseDiscountSheets($xlsx, $fileId, $shopId);
        }else{
            return false;
        }
    }

    public static function saveActionsDiscountsFileToDisk($File, $shopId, $periodFrom, $periodTo, $title)
    {
        $path = 'files/wildberries/actions';
        $name = uniqid().'-'.$File->getClientOriginalName();
        $newFile = Storage::disk('public')->putFileAs($path, $File, $name);
        $fileName = basename($newFile);

        if($fileName)
        {
            $userId = auth()->user()->id??0;
            $ProductsTempDiscountFile = new ProductsTempDiscountFile;
            $ProductsTempDiscountFile->shop_id = $shopId; // WB
            $ProductsTempDiscountFile->title = $title;
            $ProductsTempDiscountFile->filename = $fileName;
            $ProductsTempDiscountFile->period_from = Carbon::parse($periodFrom)->startOfDay();
            $ProductsTempDiscountFile->period_to = Carbon::parse($periodTo)->endOfDay();
            $ProductsTempDiscountFile->original_name = $File->getClientOriginalName();
            $ProductsTempDiscountFile->user_id = $userId;
            if($ProductsTempDiscountFile->save())
            {
                return $ProductsTempDiscountFile->id;
            };
        };

        return false;
    }

    public static function orderStickerPDF($shopId, $shopOrderId, $type = 'code128')
    {
        $WildberriesApi = self::getApi($shopId);
        return $WildberriesApi->getOrdersStickersV3([(int)$shopOrderId]);
    }

    public static function orderSticker($shopId, $shopOrderId)
    {
        $WildberriesApi = self::getApi($shopId);
        return $WildberriesApi->getOrdersStickersV3([(int)$shopOrderId]);
    }

    public static function orderSupplierSticker($shopId, $shopOrderId, $type = 'pdf')
    {
        $WildberriesApi = self::getApi($shopId);
        $supplies = $WildberriesApi->getSuppliesV3();
        foreach($supplies as $Supply)
        {
            $supplyOrders = $WildberriesApi->getSuppliesOrdersV3($Supply->id, $Supply->name);
            foreach($supplyOrders as $SupplyOrder)
            {
                if($SupplyOrder->id == $shopOrderId)
                {
                    return $WildberriesApi->suppliesBarcodeV3($Supply->id);
                }
            }
        }
        dd('У заказа нет поставки. Нужно добавить Заказ в Поставку в админке Wildberries.');
    }


    public static function uploadErrorBarcodeProducts($shopId)
    {
        $errors = (Wildberries::getApi($shopId))->contentV1CardsErrorList();
        $countErrors = count($errors);

        //dd("Ошибок: ".$countErrors);

        foreach($errors as $key => $Error)
        {
            dump("$key / $countErrors $Error->vendorCode");

            if(
                isset($Error->errors[0])
                and
                (
                    (mb_strpos($Error->errors[0], 'Неуникальный баркод: товар с баркодом') !== FALSE)
                    OR
                    ($Error->errors[0] === 'Поле Баркод не может быть пустым')
                )
            )
            {
                if($Product = Products::getProductWithPostfix($Error->vendorCode, $shopId))
                {
                    if(mb_strpos($Error->errors[0], $Error->vendorCode) !== FALSE)
                    {
                        dump($Error->vendorCode, 'Товар уже есть в WB?');
                    }else
                    {
                        $Product->barcode1 = Products::generateBarcode();
                        $Product->save();
                        Wildberries::uploadProducts($shopId, [$Product]);
                    }
                }else
                {
                    dd($Error, 'Товар по артикулу не найден');
                }
            }else
            {
                dd($Error, 'Неизвестная ошибка');
            }
        }
    }

    public static function getStoreShop($shopId)
    {
        $storeShopId = $shopId;
        if($shopId === 177)
        {
            $storeShopId = 178;
        }else if($shopId === 179)
        {
            $storeShopId = 180;
        }

        return $storeShopId;
    }

    public static function saveSalePrice($shopId)
    {
        $WbStatApi = Wildberries::getStatApi($shopId);
        $dateFrom = Carbon::now()->subMonth()->setTimezone('Europe/Moscow')->format('Y-m-d');
        $supplierSales = $WbStatApi->supplierSales($dateFrom);
        $countSupplierSales = count($supplierSales);

        foreach($supplierSales as $key => $SupplierSale)
        {
            dump("$key / $countSupplierSales");

            //$price = $SupplierSale->totalPrice * ((100 - $SupplierSale->discountPercent)/100) * ((100 - $SupplierSale->promoCodeDiscount)/100) *((100 - $SupplierSale->spp)/100);
            $price = $SupplierSale->priceWithDisc;
            $price = round($price, 2);

            if($price < 0) continue;

            if($SaleProduct = SalesProduct::whereHas('sale', function($q) use ($SupplierSale, $shopId)
            {
                $q->where(function($q) use ($SupplierSale)
                {
                    $q->where('srid', $SupplierSale->srid)->orWhere('rid', $SupplierSale->srid);
                })->where('type_shop_id', $shopId);
            })->where([
                ['status_id', '!=', 2], // not Sale status
                ['product_price', '!=', $price],
            ])->first())
            {
                $SaleProduct->product_price = $price;
                if($SaleProduct->save())
                {
                    dump("it was {$SaleProduct->sale->id}");
                }
            }else
            {
                dump("Lose $SupplierSale->srid");
            }
        }
    }


    public static function getWBSizeValue($charName, $Size)
    {
        switch($charName)
        {
            case 'Длина упаковки':
            case 'Глубина упаковки': return $Size->box_length;
            case 'Ширина упаковки': return $Size->box_width;
            case 'Высота упаковки': return $Size->box_height;
            case 'Вес товара с упаковкой (г)': return $Size->box_weight;
        }

        return false;
    }

    public static function updateWBCardSizes(&$wbCard, $Size)
    {
        $updated = false;
        $desiredChars = [
            'Длина упаковки',
            'Глубина упаковки',
            'Ширина упаковки',
            'Высота упаковки',
            'Вес товара с упаковкой (г)',
        ];
        $charsHas = [];
        foreach($wbCard->characteristics as $key => $Char)
        {
            foreach($Char as $charName => $charValue)
            {
                if(!in_array($charName, $desiredChars)) continue;

                $charsHas[] = $charName;

                if($value = self::getWBSizeValue($charName, $Size))
                {
                    if($charValue != $value)
                    {
                        $wbCard->characteristics[$key] = (object)
                        [
                            $charName => $value
                        ];

                        $updated = true;
                    }
                }
            }
        }

        if(count($charsHas) !== count($desiredChars))
        {
            if($needToAdds = array_diff($desiredChars, $charsHas))
            {
                foreach($needToAdds as $needToAddCharName)
                {
                    if($value = self::getWBSizeValue($needToAddCharName, $Size))
                    {
                        $wbCard->characteristics[] = (object)
                        [
                            $needToAddCharName => $value
                        ];

                        $updated = true;
                    }
                }
            }
        }

        return $updated;
    }
    public static function updateSizes($shopId)
    {
        $WildberriesApi = self::getApi($shopId);
        $wildberriesProducts = $WildberriesApi->contentV1CardsCursorList();
        $countProducts = count($wildberriesProducts);
        $ShopProductsSize = ShopProductsSize::where('shop_type', 'Wildberries')->first();
        $updated = 0;
        $skipped = 0;
        $error = 0;
        $errorSkus = [];

        $now = 0;

        $wildberriesProductsParts = array_chunk($wildberriesProducts, 100);

        foreach($wildberriesProductsParts as $WBPPart)
        {
            $vendorCodes = [];
            foreach($WBPPart as $WBProduct)
            {
                $vendorCodes[] = $WBProduct->vendorCode;
            }

            if($wbCards = $WildberriesApi->contentV1CardsFilter($vendorCodes))
            {
                foreach($wbCards as $wbCard)
                {
                    $now++;
                    dump("$now of $countProducts");

                    if($Product = Products::getProductWithPostfix($wbCard->vendorCode))
                    {
                        $Size = $Product->ShopBoxSizes($shopId);
                    }else
                    {
                        $Size = $ShopProductsSize;
                    }

                    if($updated = self::updateWBCardSizes($wbCard, $Size))
                    {
                        if($WildberriesApi->contentV1CardsUpdate($wbCard))
                        {
                            $updated++;
                        }else
                        {
                            $error++;
                            $errorSkus[] = $wbCard->vendorCode;
                        }
                    }else
                    {
                        $skipped++;
                    }
                }
            }
        }

        dump("Updated $updated of $countProducts, skipped: $skipped, errors: $error");
        dump("Errors skus:");
        dump($errorSkus);

        return empty($errorSkus);
    }

    public static function saveProductsStockZeroInfo($shopId)
    {
        $keyCode = uniqid();

        $WbApi = Wildberries::getApi($shopId);
        $wildberriesProducts = $WbApi->contentV1CardsCursorList();

        $skusList = [];
        $wbSkusList = [];

        $total = count($wildberriesProducts);

        foreach($wildberriesProducts as $key => $WbProduct)
        {
            dump("$key / $total");
            $WbUnknownProduct = new WbUnknownProduct;

            $WbUnknownProduct->wb_barcode = $WbProduct->sizes[0]->skus[0];
            $WbUnknownProduct->wb_sku = $WbProduct->vendorCode;


            if(!in_array($WbUnknownProduct->wb_sku, $wbSkusList))
            {
                $wbSkusList[] = $WbUnknownProduct->wb_sku;
            }else
            {
                $WbUnknownProduct->wb_double_sku = true;
            }

            if($sku = Products::getProductSkuWithPostfix($WbUnknownProduct->wb_sku, $WbApi->shopId))
                $WbUnknownProduct->recognized_sku = $sku;

            $Product = Products::getProductWithPostfix($WbUnknownProduct->wb_sku, $WbApi->shopId);
            if($Product)
            {
                $WbUnknownProduct->sku_by_sku = $Product->sku;
                $WbUnknownProduct->product_id = $Product->id;
                if(!in_array($Product->sku, $skusList))
                {
                    $skusList[] = $Product->sku;
                }else
                {
                    $WbUnknownProduct->double_sku = true;
                }
            }else
            {
                $WbUnknownProduct->unknown_sku = true;
            }

            $ProductByBarcode = Products::getProductByBarcode($WbUnknownProduct->wb_barcode)->id??false;
            if(!$ProductByBarcode)
            {
                $WbUnknownProduct->unknown_barcode = true;
            }

            if(
                $WbUnknownProduct->wb_double_sku
                or $WbUnknownProduct->double_sku
                or $WbUnknownProduct->unknown_sku
                or $WbUnknownProduct->unknown_barcode
            )
            {
                $WbUnknownProduct->key_code = $keyCode;
                $WbUnknownProduct->shop_id = $shopId;
                $WbUnknownProduct->save();
            }
        }
    }

    public static function updateUncompletedOrdersStatuses($shopId, $uncompletedOrders = false)
    {
        $updateSince = Carbon::now()->subMonths(3)->startOfDay()->setTimezone('UTC')->toDateTimeString();
        if(!$uncompletedOrders) $uncompletedOrders = Orders::getUncompleted($shopId, $updateSince);
        $countUncompletedOrders = count($uncompletedOrders);
        if($countUncompletedOrders === 0) return; // exit if no one order

        dump("К проверке статуса заказов: $countUncompletedOrders");

        $WildberriesApi = self::getApi($shopId);

        if(
            $uncompletedSystemIds = $uncompletedOrders->pluck('system_order_id')->toArray()
            and $uncompletedSystemIds
        )
        {
            if($uncompletedStatuses = $WildberriesApi->getOrderStatusesV3($uncompletedSystemIds))
            {
                foreach($uncompletedStatuses as $UncompletedStatus)
                {
                    if($OrdersInfo = OrdersInfo::whereHas('order', function($q) use ($UncompletedStatus, $shopId)
                    {
                        $q->where([
                            ['system_order_id', $UncompletedStatus->id],
                            ['type_shop_id', $shopId],
                        ]);
                    })->first())
                    {
                        $OrdersInfo->order_status_id = Orders::getStatusBySystem($UncompletedStatus->wbStatus, $WildberriesApi->systemId);
                        $OrdersInfo->systems_orders_status_id = Orders::getSystemsOrdersStatusByAlias($UncompletedStatus->wbStatus, $WildberriesApi->systemId);
                        $OrdersInfo->save();
                    }
                }
            }
        }

        dump('Обновлено');
    }


    public static function getProductsWithMaxAvailableStv($shopId, $recommendedCount, $warehousesIds)
    {
        $products = Product::with('amounts')
            ->whereHas('amounts', function($q) use ($warehousesIds)
            {
                $q
                    ->where('warehouse_id', $warehousesIds)
                    ->where('available', '>', 0);
            })
            ->whereHas('typeShopProducts', function($q) use ($shopId)
            {
                $q->where('type_shop_id', $shopId);
            })
            ->get();

        $products = $products->sortBy(
            function($a)
            {
                return -$a->amounts->where('warehouse_id', 1)->first()->available;
            }
        );

        return $products->take($recommendedCount * 2);
    }
    public static function addToMaxRecommended(&$recommendedProducts, $topProducts, $Product, $recommendedCount)
    {
        $hasSkus = array_column($recommendedProducts, 'id');
        $hasSkus[] = $Product->id;

        foreach($topProducts as $TopProduct)
        {
            if(count($recommendedProducts) < $recommendedCount)
            {
                if(!in_array($TopProduct->id, $hasSkus))
                {
                    $recommendedProducts[] = $TopProduct;
                }
            }else
            {
                break;
            }
        }
    }

    public static function updateRecommendedForProduct($shopId, $Product, $recommendedProducts)
    {
        $WbRecommendedApi = Wildberries::getApi($shopId);

        $nmId = (int) $Product->typeShopProducts->where('type_shop_id', $shopId)->first()->shop_product_id;

        $toAdd = [];
        foreach($recommendedProducts as $RProduct)
        {
            $toAdd[] = (int) $RProduct->typeShopProducts->where('type_shop_id', $shopId)->first()->shop_product_id;
        }

        if($sups = $WbRecommendedApi->recommendedSup($nmId))
        {
            $toDel = [];
            foreach($sups as $sup)
            {
                if(!in_array($sup, $toAdd))
                {
                    $toDel[] = $sup;
                }
            }

            if(count($toDel) > 0)
                $WbRecommendedApi->recommendedDel($nmId, $toDel);
        }

        $WbRecommendedApi->recommendedIns($nmId, $toAdd);
    }

    public static function updateRecommendedProducts($shopId, $products = [])
    {
        $recommendedCount = 7;

        $warehousesIds = Shops::getShopWarehousesIds($shopId);

        $WbApi = Wildberries::getApi($shopId);
        $wildberriesProducts = $WbApi->contentV1CardsCursorList();

        $topProducts = self::getProductsWithMaxAvailableStv($WbApi->shopId, $recommendedCount, $warehousesIds);

        if($products)
            self::filterByProducts($wildberriesProducts, $products);

        $countProducts = count($wildberriesProducts);
        $i = 0;

        foreach($wildberriesProducts as $key => $WildberriesProduct)
        {
            $i++;
            dump("$i of $countProducts $WildberriesProduct->vendorCode");
            $recommendedProducts = [];

            if(!$Product = Products::getProductWithPostfix($WildberriesProduct->vendorCode, $shopId))
                continue;

            $productsToRecommended = Product::with('amounts')
                ->whereHas('amounts', function($q) use ($warehousesIds)
                {
                    $q
                        ->whereIn('warehouse_id', $warehousesIds)
                        ->where('available', '>', 0);
                })
                ->where('group_id', $Product->group_id)
                ->where('id', '!=', $Product->id)
                ->whereHas('typeShopProducts', function($q) use ($WbApi)
                {
                    $q->where('type_shop_id', $WbApi->shopId);
                })
                ->get();

            $productsToRecommended = $productsToRecommended->sortBy(
                function($a) use ($warehousesIds)
                {
                    return -$a->amounts->whereIn('warehouse_id', $warehousesIds)->sum('available');
                }
            );

            foreach($productsToRecommended as $ProductRecommended)
            {
                if(count($recommendedProducts) < $recommendedCount)
                {
                    if($ProductRecommended->amounts->whereIn('warehouse_id', $warehousesIds)->sum('available') > 0)
                    {
                        $recommendedProducts[] = $ProductRecommended;
                    }
                }else
                {
                    break;
                }
            }

            if(count($recommendedProducts) < $recommendedCount)
                self::addToMaxRecommended($recommendedProducts, $topProducts, $Product, $recommendedCount);

            self::updateRecommendedForProduct($shopId, $Product, $recommendedProducts);
        }


    }
}


