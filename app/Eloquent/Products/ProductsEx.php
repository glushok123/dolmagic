<?php

namespace App\Eloquent\Products;

use App\Eloquent\Other\Ozon\OzonCommission;
use App\Eloquent\Shops\ShopPrice;
use App\Models\Prices\Price;
use App\Models\Products;
use App\Models\Warehouses;
use Illuminate\Database\Eloquent\Model;

class ProductsEx extends Model
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



    public function shopPrices()
    {
        return $this->hasMany('App\Eloquent\Shops\ShopPrice', 'product_id', 'id');
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
        $sizes->weight = $this->box_width;
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

        return $sizes;
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
}
