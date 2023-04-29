<?php

namespace App\Eloquent\Shops;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ShopUnloading extends Model
{
    public function shop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'shop_id');
    }

    public function upPrices()
    {
        return $this->hasMany('App\Eloquent\Shops\ShopUnloadingsUpPrice', 'shop_unloading_id', 'id')->orderBy('price_min')->orderBy('price_max');
    }

    public function productsTitles()
    {
        return $this->hasMany('App\Eloquent\Shops\ShopUnloadingsProductsTitle', 'shop_unloading_id', 'id');
    }
    public function productsGroups()
    {
        return $this->hasMany('App\Eloquent\Shops\ShopUnloadingsProductsGroup', 'shop_unloading_id', 'id');
    }

    public function productsOptions()
    {
        return $this->hasMany('App\Eloquent\Shops\ShopUnloadingsProductsOption', 'shop_unloading_id', 'id');
    }

    public function mainShopUnloading()
    {
        return $this->hasOne('App\Eloquent\Shops\ShopUnloading', 'id', 'get_options_from_id');
    }






    public function getCreatedDatetimeAttribute()
    {
        return Carbon::parse($this->created_at)->setTimezone('Europe/Moscow')->toDateTimeString();
    }

    public function getUpdatedDatetimeAttribute()
    {
        return Carbon::parse($this->updated_at)->setTimezone('Europe/Moscow')->toDateTimeString();
    }

    public function getYMLAttribute()
    {
        $ymlUrl = route('shop.unloading.yml', ['unloadingId' => $this->id]);
        $ymlUrl = str_replace('crmdollmagic.ru', 's1.crmdollmagic.ru', $ymlUrl);
        return $ymlUrl;
    }

    public function getLocalYmlPathAttribute()
    {
        return "xml/unloadings/$this->id.xml";
    }
}
