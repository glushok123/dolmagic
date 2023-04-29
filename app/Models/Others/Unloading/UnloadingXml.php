<?php

namespace App\Models\Others\Unloading;

use App\Eloquent\Products\Product;
use App\Eloquent\Products\ProductCategory;
use App\Eloquent\Shops\ShopUnloading;
use App\Models\Model;
use App\Models\Prices\Price;
use App\Models\Products;
use Carbon\Carbon;
use DOMDocument;
use Illuminate\Support\Facades\Storage;

class UnloadingXml extends Model
{

    public static function saveAllYml()
    {
        $shopUnloadings = ShopUnloading::where('yml_from_crm', 1)->get();
        foreach($shopUnloadings as $ShopUnloading)
        {
            self::saveYml($ShopUnloading);
        }
    }

    public static function addChildWithValue(&$yml, &$parent, $childName, $value)
    {
        $child = $yml->createElement($childName);
        $child = $parent->appendChild($child);

        $valueChild = $yml->createTextNode($value);
        $child->appendChild($valueChild);

        return $child;
    }

    public static function formYmlOffers(&$yml, &$offers, $ShopUnloading)
    {
        $products = Product::where([
            ['archive', 0],
            ['state', '>', '-1'],
            ['temp_price', '>', 0]
        ])
            ->whereHas('images')
            //->limit(3000)
            ->where('sku', '!=', '573753')
            ->where(function($q){
                $q
                    ->where('condition', '!=', 'Уценка')
                    ->orWhereNull('condition');
            })
            ->where(function($q){
                $q
                    ->where('markdown_reason', '')
                    ->orWhereNull('markdown_reason');
            })
            ->orderBy('created_at', 'DESC');

        if(in_array($ShopUnloading->shop_id, [67, 71, 72, 73, 174, 176, 177, 179])) // yandex, wb
        {
            $products->where('yandex_not_upload', 0);
        }

        $products = $products->get();


        foreach($products as $Product)
        {
            $balance = $Product->shopAmounts($ShopUnloading->shop_id)->amounts->balance??0;

            $offer = $yml->createElement('offer');
            $offer = $offers->appendChild($offer);

            $offer->setAttribute('available', ($balance > 0)?'true':'false');
            $offer->setAttribute('id', $Product->sku);

            self::addChildWithValue($yml, $offer, 'barcode', $Product->barcode);

            self::addChildWithValue($yml, $offer, 'price', Price::recalculatePriceByUnloadingOption($Product->temp_price, $ShopUnloading->shop_id));

            if($Product->temp_old_price)
            {
                $oldPrice = $yml->createElement('oldprice');
                $oldPrice = $offer->appendChild($oldPrice);

                $oldPrice->nodeValue = Price::recalculatePriceByUnloadingOption($Product->temp_old_price, $ShopUnloading->shop_id);
            }

            self::addChildWithValue($yml, $offer, 'currencyId', 'RUB');

            self::addChildWithValue($yml, $offer, 'categoryId', $Product->category_id);

            $images = Products::getShopImages($Product, $ShopUnloading->shop_id);
            foreach($images as $ProductImage)
            {
                //self::addChildWithValue($yml, $offer, 'picture', $ProductImage->ShortLink);
                //self::addChildWithValue($yml, $offer, 'picture', $ProductImage->ShortLinkAway);
                self::addChildWithValue($yml, $offer, 'picture', $ProductImage->ShortLink);
                // https://shop.dollmagic.ru/loadUrl.php?url=product-image/33776.gif
            }

            self::addChildWithValue($yml, $offer, 'store', 'true');
            self::addChildWithValue($yml, $offer, 'pickup', 'true');
            self::addChildWithValue($yml, $offer, 'delivery', 'true');

            self::addChildWithValue($yml, $offer, 'name', Unloading::getYmlProductName($Product, $ShopUnloading->id));

            self::addChildWithValue($yml, $offer, 'vendor', $Product->manufacturer->name);
            self::addChildWithValue($yml, $offer, 'vendorCode', $Product->sku);
            self::addChildWithValue($yml, $offer, 'description', $Product->temp_short_description);

            self::addChildWithValue($yml, $offer, 'vat', 'NO_VAT');


            self::addChildWithValue($yml, $offer, 'weight', $Product->BoxSizes->valueWeightKg);
            self::addChildWithValue($yml, $offer, 'dimensions',
                "{$Product->BoxSizes->valueLength}/{$Product->BoxSizes->valueWidth}/{$Product->BoxSizes->valueHeight}");

            self::addChildWithValue($yml, $offer, 'period-of-validity-days', Unloading::getPeriodOfValidityDays());


            $outlets = $yml->createElement('outlets');
            $outlets = $offer->appendChild($outlets);

            $outlet = $yml->createElement('outlet');
            $outlet->setAttribute('id', '0');
            $outlet->setAttribute('instock', $balance);
            $outlet = $outlets->appendChild($outlet);
        }


        /*
        <url>https://dollmagic.ru/collection/monster_high/product/nabor-hit-berns-i-ebbi-bomineybl</url>
        */
    }

    public static function formYmlCategories(&$yml, &$categories, $ShopUnloading)
    {
        $productCategories = ProductCategory::where('state', '>', -1)->get();
        foreach($productCategories as $ProductCategory)
        {
            $child = self::addChildWithValue($yml, $categories, 'category', $ProductCategory->name);
            $child->setAttribute('id', $ProductCategory->id);
        }
    }

    public static function saveYml($ShopUnloading)
    {
        $yml = new DOMDocument();
        $yml->encoding = 'utf-8';
        $yml->xmlVersion = '1.0';
        $yml->formatOutput = true;

        $ymlCatalog = $yml->createElement('yml_catalog');
        $ymlCatalog->setAttribute('date', Carbon::now('Europe/Moscow')->format('Y-m-d H:i'));
        $ymlCatalog = $yml->appendChild($ymlCatalog);

        $shop = $yml->createElement('shop');
        $shop = $ymlCatalog->appendChild($shop);

        self::addChildWithValue($yml, $shop, 'company', 'ИП Деркач Ирина Леонидовна');
        self::addChildWithValue($yml, $shop, 'name', 'Магия кукол');
        self::addChildWithValue($yml, $shop, 'url', 'https://dollmagic.ru');
        self::addChildWithValue($yml, $shop, 'platform', 'crmdollmagic.ru');

        $currencies = $yml->createElement('currencies');
        $currencies = $ymlCatalog->appendChild($currencies);
        $currency = $yml->createElement('currency');
        $currency->setAttribute('id', 'RUB');
        $currency->setAttribute('rate', '1.0');
        $currency = $currencies->appendChild($currency);

        $categories = $yml->createElement('categories');
        $categories = $shop->appendChild($categories);
        self::formYmlCategories($yml, $categories, $ShopUnloading);

        $offers = $yml->createElement('offers');
        $offers = $shop->appendChild($offers);
        self::formYmlOffers($yml, $offers, $ShopUnloading);

        Storage::disk('public')->put($ShopUnloading->LocalYmlPath, $yml->saveXML());
    }
}


