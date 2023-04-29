<?php

namespace App\Eloquent\Products;

use App\Eloquent\Other\Ozon\OzonCommission;
use App\Eloquent\Shops\ShopPrice;
use App\Eloquent\Shops\ShopProductsSize;
use App\Models\Prices\Price;
use App\Models\Products;
use App\Models\Warehouses;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = array();

    protected $casts = [
        'purchase_average_price' => 'float',
        'temp_price' => 'float',
        'temp_old_price' => 'float',
    ];

    /*
    public function info()
    {
        return $this->hasOne('App\Eloquent\OrdersInfo');
    }
*/


    public function imagesShopRules()
    {
        return $this->hasMany('App\Eloquent\Products\ProductImagesShopRule', 'product_id', 'id');
    }


    public function shopPrices()
    {
        return $this->hasMany('App\Eloquent\Products\ProductsShopPrice', 'product_id', 'id');
    }
    public function shopPrice($shopId)
    {
        return $this->shopPrices->where('shop_id', $shopId)->first();
    }

    public function shopCategories()
    {
        return $this->hasMany('App\Eloquent\Products\ProductShopCategory', 'product_id', 'id');
    }
    public function shopCategory($shopId)
    {
        return $this->shopCategories->where('shop_id', $shopId)->first();
    }



    public function priceByUnloadingOption($shopId)
    {
        return (int) Price::recalculatePriceByUnloadingOption($this->temp_price, $shopId);
    }

    public function oldPriceByUnloadingOption($shopId)
    {
        return (int) Price::recalculatePriceByUnloadingOption($this->temp_old_price, $shopId);
    }

    public function type()
    {
        return $this->hasOne('App\Eloquent\Products\ProductType', 'id', 'type_id');
    }

    public function category()
    {
        return $this->hasOne('App\Eloquent\Products\ProductCategory', 'id', 'category_id');
    }

    public function material()
    {
        return $this->hasOne('App\Eloquent\Products\ProductMaterial', 'id', 'material_id');
    }

    public function manufacturer()
    {
        return $this->hasOne('App\Eloquent\Products\Manufacturer', 'id', 'manufacturer_id');
    }

    public function producingCountry()
    {
        return $this->hasOne('App\Eloquent\Products\ProducingCountry', 'id', 'producing_country_id');
    }

    public function character()
    {
        return $this->hasOne('App\Eloquent\Products\ProductCharacter', 'id', 'character_id');
    }

    public function brand()
    {
        return $this->hasOne('App\Eloquent\Products\ProductCharacter', 'id', 'character_id');
    }

    public function feature()
    {
        return $this->hasOne('App\Eloquent\Products\ProductFeature', 'id', 'feature_id');
    }

    public function group()
    {
        return $this->hasOne('App\Eloquent\Products\ProductGroup', 'id', 'group_id');
    }

    public function images()
    {
        return $this->hasMany('App\Eloquent\Products\ProductImage', 'product_id')->orderBy('position', 'ASC');
    }

    public function amounts()
    {
        return $this->hasMany('App\Eloquent\Warehouse\WarehouseProductsAmount', 'product_id', 'id');
    }

    public function shopAmounts($typeShopId)
    {
        return Warehouses::getShopAmounts($this, $typeShopId);
    }

    public function shopStop($typeShopId)
    {
        return Products::getSystemsProductsStopResult($this, $typeShopId);
    }

    public function systemsProductsStops()
    {
        return $this->hasMany('App\Eloquent\System\SystemsProductsStop', 'product_id', 'id');
    }

    public function systemsProductsStop($shopId)
    {
        return $this->systemsProductsStops->where('orders_type_shop_id', $shopId)->first();
    }

    public function typeShopProducts()
    {
        return $this->hasMany('App\Eloquent\Products\TypeShopProduct', 'product_id', 'id');
    }

    public function saleProducts()
    {
        return $this->hasMany('App\Eloquent\Sales\SalesProduct', 'product_id', 'id');
    }

    public function warehouseMovementProducts()
    {
        return $this->hasMany('App\Eloquent\Warehouse\WarehouseMovementProduct', 'product_id', 'id');
    }






    public function getWarehouseAvailable($warehouseId)
    {
        $available = $this
            ->hasOne('App\Eloquent\Warehouse\WarehouseProductsAmount', 'product_id', 'id')
            ->where('warehouse_id', '=', $warehouseId)
            ->first();
        $available = $available?$available->available:0;
        return $available;
    }

    public function getShopOption($shopId)
    {
        return $this
            ->hasOne('App\Eloquent\Products\TypeShopProductsOption', 'product_id', 'id')
            ->where('shop_id', $shopId)
            ->first();
    }

    public function shopOptions()
    {
        return $this->hasMany('App\Eloquent\Products\TypeShopProductsOption', 'product_id', 'id');
    }

    //Attributes

    public function getImageAttribute()
    {
        return $this->images[0]??false;
    }

    public function getClassAttribute()
    {
        $statusClass = '';
        switch($this->attributes['state']){
            case '-1':
                $statusClass = 'table-danger';
                break;
            case '0':
                $statusClass = 'table-warning';
                break;
            case '1':

                break;
            case '2':
                $statusClass = 'table-success';
            break;
        };
        return $statusClass;
    }

    public function getEditPathAttribute()
    {
        return route('products.edit', ['id' => $this->id]);
    }

    public function getAmountAttribute()
    {
        //return $this->amounts->
    }

    public function getArchiveClassAttribute()
    {
        $class = '';
        switch ($this->archive)
        {
            case 1:
                $class = 'badge-danger';
                break;
            case 0:
                $class = 'badge-success';
                break;
        }
        return $class;
    }


    public function getBoxSizesAttribute(): \stdClass
    {
        $sizes = new \stdClass();
        $sizes->weight = $this->weight;
        $sizes->defaultWeight = $this->category?$this->category->temp_weight:0;

        $sizes->width = $this->box_width;
        $sizes->defaultWidth = $this->category?$this->category->temp_width:0;

        $sizes->height = $this->box_height;
        $sizes->defaultHeight = $this->category?$this->category->temp_height:0;

        $sizes->length = $this->box_length;
        $sizes->defaultLength = $this->category?$this->category->temp_depth:0;


        $sizes->valueWeight = $this->weight?:$sizes->defaultWeight;

        $sizes->valueWidth = $sizes->width?:$sizes->defaultWidth;
        $sizes->valueHeight = $sizes->height?:$sizes->defaultHeight;
        $sizes->valueLength = $sizes->length?:$sizes->defaultLength;

        $sizes->liters = $sizes->valueLength * $sizes->valueWidth * $sizes->valueHeight / 1000;
        $sizes->litersDesc = "Литры: $sizes->valueLength * $sizes->valueWidth * $sizes->valueHeight / 1000 = {$sizes->liters}л.";

        $sizes->volumeWeight = round($sizes->liters / 5, 3);
        $sizes->volumeWeightRounded = round($sizes->volumeWeight, 1);
        $sizes->volumeWeightDesc = "$sizes->litersDesc".PHP_EOL."Объёмный вес: $sizes->liters / 5 = {$sizes->volumeWeight}кг.";
        $sizes->valueWeightKg = $sizes->valueWeight / 1000; // /1000 g -> kg

        $sizes->clearEstimatedWeight = ($sizes->volumeWeight < $sizes->valueWeightKg)?$sizes->valueWeightKg:$sizes->volumeWeight;
        $sizes->estimatedWeight = round($sizes->clearEstimatedWeight,1);  // 0.1kg


        // new real sizes from 2022-04-04
        $sizes->realBoxLength = $this->real_box_length;
        $sizes->valueRealBoxLength = $sizes->realBoxLength?:$sizes->valueLength;

        $sizes->realBoxWidth = $this->real_box_width;
        $sizes->valueRealBoxWidth = $sizes->realBoxWidth?:$sizes->valueWidth;

        $sizes->realBoxHeight = $this->real_box_height;
        $sizes->valueRealBoxHeight = $sizes->realBoxHeight?:$sizes->valueHeight;

        $sizes->realBoxWeight = $this->real_box_weight;
        $sizes->valueRealBoxWeight = $sizes->realBoxWeight?:$sizes->valueWeight;

        $sizes->realBoxWeightKg = $this->real_box_weight / 1000;
        $sizes->valueRealBoxWeightKg = $sizes->realBoxWeightKg?:$sizes->valueWeightKg;

        // test if ok
        $sizes->shops = [];
        if($shopProductsSizes = ShopProductsSize::where('product_id', $this->id)->get())
        {
            foreach($shopProductsSizes as $ShopProductSize)
            {
                $sizes->shops[$ShopProductSize->shop_id] = $ShopProductSize;
            }
        }

        return $sizes;
    }

    public function ShopBoxSizes($shopId = false): \stdClass
    {
        $ShopBoxSizes = new \stdClass();
        $ShopBoxSizes->box_weight = $this->BoxSizes->valueWeight;
        $ShopBoxSizes->box_length = $this->BoxSizes->valueLength;
        $ShopBoxSizes->box_width = $this->BoxSizes->valueWidth;
        $ShopBoxSizes->box_height = $this->BoxSizes->valueHeight;

        if(in_array($shopId, [177, 179])) // WB
        {
            if($ShopProductsSize = ShopProductsSize
                ::where('shop_type', 'Wildberries')
                ->where('size_type', 'default')
                ->first()
            )
            {
                if($ShopProductsSize->box_weight) $ShopBoxSizes->box_weight = $ShopProductsSize->box_weight;
                if($ShopProductsSize->box_length) $ShopBoxSizes->box_length = $ShopProductsSize->box_length;
                if($ShopProductsSize->box_width) $ShopBoxSizes->box_width = $ShopProductsSize->box_width;
                if($ShopProductsSize->box_height) $ShopBoxSizes->box_height = $ShopProductsSize->box_height;
            }
        }

        // only if not set MANUAL SIZES
        //if(in_array($shopId, [177, 179])) // WB
        if(in_array($shopId, [177])) // WB
        {
            $DateNow = Carbon::now('Europe/Moscow');
            if($DateNow->dayOfWeek === Carbon::MONDAY)  // ONLY MONDAY
            {
                $from = Carbon::now('Europe/Moscow')->setTime(5, 0);
                $to = Carbon::now('Europe/Moscow')->setTime(19, 0);
                if($DateNow->between($from, $to)) // ONLY FROM 05:00 to 19:00)
                {
                    if(
                        $ShopProductsSize = ShopProductsSize
                            ::where('shop_type', 'Wildberries')
                            ->where('size_type', 'oversized')
                            ->first()
                    )
                    {
                        if($ShopProductsSize->box_weight) $ShopBoxSizes->box_weight = $ShopProductsSize->box_weight;
                        if($ShopProductsSize->box_length) $ShopBoxSizes->box_length = $ShopProductsSize->box_length;
                        if($ShopProductsSize->box_width) $ShopBoxSizes->box_width = $ShopProductsSize->box_width;
                        if($ShopProductsSize->box_height) $ShopBoxSizes->box_height = $ShopProductsSize->box_height;

                        return $ShopBoxSizes;
                    }
                }
            }
        }


        if(
            (
                $ShopProductsSize = ShopProductsSize
                ::where('product_id', $this->id)
                ->where('shop_id', $shopId)
                ->first()
            )
            and
            (
                $ShopProductsSize->box_weight
                or $ShopProductsSize->box_length
                or $ShopProductsSize->box_width
                or $ShopProductsSize->box_height
            )
        )
        {
            if($ShopProductsSize->box_weight) $ShopBoxSizes->box_weight = $ShopProductsSize->box_weight;
            if($ShopProductsSize->box_length) $ShopBoxSizes->box_length = $ShopProductsSize->box_length;
            if($ShopProductsSize->box_width) $ShopBoxSizes->box_width = $ShopProductsSize->box_width;
            if($ShopProductsSize->box_height) $ShopBoxSizes->box_height = $ShopProductsSize->box_height;
        }

        return $ShopBoxSizes;
    }

    public function reloads()
    {
        return $this->hasMany('App\Eloquent\Shops\Products\ShopProductsReload', 'product_id', 'id');
    }

    public function reload($shopId)
    {
        return $this
            ->reloads
            ->where('shop_id', $shopId)
            ->first();
    }

    public function shopProductId($shopId)
    {
        return $this
                ->hasMany('App\Eloquent\Products\TypeShopProduct', 'product_id', 'id')
                ->where('type_shop_id', $shopId)
                ->first()
                ->shop_product_id??false;
    }

    public function shopRulesImages($shopRuleId)
    {
        $ProductImagesShopRule = ProductImagesShopRule::where('id', $shopRuleId)->first();
        $shopRuleImagesIds = [];
        $images = [];
        foreach($ProductImagesShopRule->positions as $ProductImagesShopRulePosition)
        {
            if($ProductImage = ProductImage::where('id', $ProductImagesShopRulePosition->image_id)->first())
            {
                $ProductImage->hide = $ProductImagesShopRulePosition->hide;
                $images[] = $ProductImage;
                $shopRuleImagesIds[] = $ProductImagesShopRulePosition->image_id;
            }else
            {
                $ProductImagesShopRulePosition->delete(); // temp
            }
        }

        $otherImages = ProductImage::where([
            ['product_id', $this->id],
        ])->whereNotIn('id', $shopRuleImagesIds)->orderBy('position', 'ASC')->get();

        foreach($otherImages as $OtherImage)
        {
            $images[] = $OtherImage;
        }

        return $images;
    }

    public function getBarcodeAttribute()
    {
        if($this->barcode1) return $this->barcode1;
        if($this->barcode2) return $this->barcode2;
        if($this->barcode3) return $this->barcode3;
        if($this->barcode4) return $this->barcode4;
        if($this->dummy_barcode) return $this->dummy_barcode;

        return '';
    }
    public function getBarcodesAttribute()
    {
        $barcodes = [];
        if($this->barcode1) $barcodes[] = $this->barcode1;
        if($this->barcode2) $barcodes[] = $this->barcode2;
        if($this->barcode3) $barcodes[] = $this->barcode3;
        if($this->barcode4) $barcodes[] = $this->barcode4;

        if(empty($barcodes) and $this->dummy_barcode) $barcodes[] = $this->dummy_barcode;

        return $barcodes;
    }

    public function getClearDescriptionAttribute()
    {
        $desc = html_entity_decode(strip_tags($this->temp_short_description));
        $desc = str_replace('&amp;', '&', $desc);
        $desc = str_replace('&nbsp;', ' ', $desc);
        $desc = str_replace(' ', ' ', $desc);
        $desc = str_replace('​', ' ', $desc);
        $desc = str_replace('  ', ' ', $desc);

        $desc = str_replace('«', '"', $desc);
        $desc = str_replace('»', '"', $desc);
        $desc = str_replace('–', '-', $desc);


        return $desc;
    }

    public function getWBSkuAttribute()
    {
        return $this->sku . '-WB';
    }



    public function actualPrice($shopId)
    {
        $ActualPrice = new \stdClass();

        $ActualPrice->price = $this->priceByUnloadingOption($shopId);
        $ActualPrice->unloading_price = $ActualPrice->price;
        $ActualPrice->manual_price = false;

        $ActualPrice->old_price = $this->oldPriceByUnloadingOption($shopId);
        $ActualPrice->unloading_old_price = $ActualPrice->price;
        $ActualPrice->manual_old_price = false;

        $ActualPrice->manual_user = false;

        if($ShopPrice = $this->shopPrice($shopId))
        {
            if($ShopPrice->price)
            {
                $ActualPrice->price = $ShopPrice->price;
                $ActualPrice->manual_price = true;
            }
            if($ShopPrice->old_price)
            {
                $ActualPrice->old_price = $ShopPrice->old_price;
                $ActualPrice->manual_old_price = true;
            }

            $ActualPrice->manual_user = $ShopPrice->user;
        }

        return $ActualPrice;
    }
}

