<?php

namespace App\Eloquent\Order;

use App\Eloquent\Products\ProductsName;
use Illuminate\Database\Eloquent\Model;

class OrdersTypeShop extends Model
{
    public $timestamps = false;

    public function warehouse()
    {
        return $this->hasOne('App\Eloquent\Warehouse\Warehouse', 'id', 'warehouse_id');
    }



    public function system()
    {
        return $this->hasOne('App\Eloquent\System\System', 'id', 'system_id');
    }

    public function shopUnloading()
    {
        return $this->hasOne('App\Eloquent\Shops\ShopUnloading', 'shop_id', 'id');
    }

    public function salesFinancesDefaultListServices()
    {
        return $this->hasMany('App\Eloquent\Sales\Finances\SalesFinancesDefaultListService', 'shop_id', 'id');
    }

    public function getLogoUrlAttribute(): string
    {
        return '/images/shops/logo/'.$this->logo;
    }

    public function getLogoImgAttribute(): string
    {
        return "
            <img
                src = '$this->LogoUrl'
                alt = '$this->name'
                title = '$this->name'
            /> $this->name
        ";
    }



    public function ProductName($productId)
    {
        return ProductsName::where([
            ['shop_id', $this->id],
            ['product_id', $productId],
        ])->first();
    }
}
