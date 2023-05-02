<?php

namespace App\Console\Api;

use App\Eloquent\Products\Product;
use App\Eloquent\Products\TypeShopProduct;
use App\Models\Others\Ozon;
use App\Models\Prices\Price;

class OzonApi2 extends OzonApi
{
    public $Warehouses = array(
        'Stavropol' => array(
            'id' => 1,
            'token' => '228055',
            'pass' => '916f79ce-aaac-420f-b909-dbeeedf8a6c5',
            'typeShopId' => 10001,

            'ozonDeliveryWarehouseId' => false,
            'crmDeliveryWarehouseId' => '22318950779000',
        ),
        'Moscow' => array(
            'id' => 2,
            'token' => '535014',
            'pass' => 'de695e5b-71cc-4752-87fa-657037702b7b',
            'typeShopId' => 10002,

            'ozonDeliveryWarehouseId' => false,
            'crmDeliveryWarehouseId' => '23851995857000',
        )
    );

    public function  __construct($alias)
    {
        parent::__construct($alias);
    }

    public function copyProductsFromSTVtoSTV2($products = []): bool
    {
        $productsSku = [];
        $OzonSTVApi = Ozon::getOzonApiByShopId(1);
        if($products)
        {
            $productsSku = $products->pluck('sku')->toArray();
        }else
        {
            $ozonProducts = $OzonSTVApi->getProductsListV2();
            foreach($ozonProducts as $OzonProduct)
            {
                $productsSku[] = $OzonProduct->offer_id;
            }
            unset($ozonProducts);
        }
        if(!$productsSku)
        {
            dump("Hasn't products sku");
            return false;
        }

        $productsInfo = $OzonSTVApi->getProductsInfo($productsSku);

        $req = ['offer_id' => $productsSku];

        $ozonTemplates = $OzonSTVApi->getAttributesV3($req, false);

        foreach($ozonTemplates as $key => $OzonTemplate)
        {
            $Product = Product::where('sku', $OzonTemplate->offer_id)->first();
            if(!$Product)
            {
                unset($ozonTemplates[$key]);
                continue;
            }

            if(($Reload = $Product->reload($this->shopId)) and $Reload->reload)
            {
                unset($ozonTemplates[$key]);
                continue;
            }

            $peelOffArray = [
                '573784', '571810', '6061836', 'CHR73',

                '575702', 'GWD87', 'F5120', '569619', '559887', '572077'
            ];

            // temp unset skus
            if(in_array($Product->sku, $peelOffArray))
            {
                unset($ozonTemplates[$key]);
                continue;
            }

            // Don't update exists products on OzonSTV-2 by Irina 2021-11-22 00:43
            $TypeShopProduct = TypeShopProduct::where([
                ['type_shop_id', $this->shopId],
                ['product_id', $Product->id],
            ])->first();
            if($TypeShopProduct)
            {
                unset($ozonTemplates[$key]);
                continue;
            }

            $images = [];
            foreach($OzonTemplate->images as $iKey => $OzonTemplateImage)
            {
                if($iKey === 0) $OzonTemplate->primary_image = $OzonTemplateImage->file_name;
                $images[] = $OzonTemplateImage->file_name;
            }
            $OzonTemplate->images = $images;

            unset($OzonTemplate->id);

            if(!$OzonTemplate->barcode)
            {
                if($Product->barcode)
                {
                    $OzonTemplate->barcode = $Product->barcode;
                }else
                {
                    foreach($productsInfo as $iKey => $ProductInfo)
                    {
                        if(($Product->sku === $ProductInfo->offer_id) and $ProductInfo->barcode)
                        {
                            $OzonTemplate->barcode = $ProductInfo->barcode;
                            unset($productsInfo[$iKey]);
                        }
                    }
                }
            }

            if($OzonTemplate->barcode)
            {
                $idCode = str_pad($Product->id, '6', '0', STR_PAD_LEFT);
                $OzonTemplate->barcode = "$OzonTemplate->barcode$idCode";
                $OzonTemplate->barcode = substr($OzonTemplate->barcode, (strlen($OzonTemplate->barcode) - 13), 13);
            }

            // clear attributes
            foreach($OzonTemplate->attributes as $Attribute)
            {
                if($Attribute->attribute_id === 85)
                {
                    if($Product->character and $Product->character->ozon2_name)
                    {
                        unset($Attribute->complex_id);
                        $Attribute->values[0]->dictionary_value_id = 0;
                        $Attribute->values[0]->value = $Product->character->ozon2_name;
                    }
                }
                $Attribute->id = $Attribute->attribute_id;
                unset($Attribute->attribute_id);
            }

            $OzonTemplate->vat = $this->importVat;
            $OzonTemplate->price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->shopId);
            if($Product->temp_old_price !== 0)
                $OzonTemplate->old_price = Price::recalculatePriceByUnloadingOption($Product->temp_old_price, $this->shopId);
            if($premiumPrice = $this->getPremiumPrice($OzonTemplate->price))
                $OzonTemplate->premium_price = $premiumPrice;

            /*
            // remove attributes to double info
            $TypeShopProduct = TypeShopProduct::where([
                ['type_shop_id', $this->shopId],
                ['product_id', $Product->id],
            ])->first();
            if($TypeShopProduct)
            {
                unset($OzonTemplate->name);
                foreach($OzonTemplate->attributes as $aKey => $Attribute)
                {
                    if(in_array($Attribute->id, [9048, 4191, 4180]))
                    {
                        // name
                        // "attribute_id": 9048 // Model Name 4180 ??
                        // "attribute_id": 4191 // desc
                        unset($OzonTemplate->attributes[$aKey]);
                    }
                }

                $OzonTemplate->attributes = array_values($OzonTemplate->attributes);
            }
            dd($OzonTemplate);
            */
        }

        // send ready items
        $chunkedItems = array_chunk($ozonTemplates, 100);

        foreach($chunkedItems as $items)
        {
            $req = ['items' => $items];
            $res = $this->makeRequest(
                'POST',
                "/v2/product/import",
                $req
            );

            dump($res);
        }

        return true;
    }
}
