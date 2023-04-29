<?php

namespace App\Eloquent\Products;

use App\Models\Others\Wildberries\Wildberries;
use App\Models\Products;
use Illuminate\Database\Eloquent\Model;

class ProductsTempDiscountFileValue extends Model
{
    protected $guarded = array();

    protected $casts = [
        'planned_price' => 'float',
        'new_price' => 'float',
        'new_discount' => 'float',
    ];

    public function file()
    {
        return $this->hasOne('App\Eloquent\Products\ProductsTempDiscountFile', 'id', 'file_id');
    }

    public function shop()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersTypeShop', 'id', 'shop_id');
    }

    public function product()
    {
        return $this->hasOne('App\Eloquent\Products\Product', 'id', 'product_id');
    }

    /*
    public function getProductAttribute()
    {
        $Product = Products::getProductWithPostfix($this->sku, $this->shop_id);

        return $Product;
    }
    */

    public function getWBProductPriceAttribute()
    {
        $res = 0;
        if($Price = $this->product?Wildberries::getPrice($this->product, $this->shop_id):false)
        {
            $res = $Price->price - ($Price->price*$Price->discount/100);
        }

        return $res;
    }

    public function getSystemProductStopAttribute()
    {
        $shopForDiscount = ($this->shop_id === 177)?2:1;
        $ProductStop = false;
        if($productStops = $this->product?Products::getSystemsProductsStops($this->product, $shopForDiscount):false)
        {
            $ProductStop = $productStops->first();
        }

        return $ProductStop;
    }

    public function getMaxDiscountAttribute()
    {
        if($this->SystemProductStop)
        {
            if($this->SystemProductStop->ozon_auto_action_max_discount !== NULL)
            {
                return $this->SystemProductStop->ozon_auto_action_max_discount;
            }
        }

        return 100;
    }

    public function getDiscountRubAttribute()
    {
        return $this->planned_price - $this->WBProductPrice;
    }

    public function getDiscountPercentAttribute()
    {
        $WBPrice = $this->WBProductPrice;
        if($WBPrice !== 0)
        {
            return ceil((100 - ($this->planned_price / $this->WBProductPrice) * 100));
        }else
        {
            return '-';
        }
    }

    public function getParam1Attribute()
    {
        $param = new \stdClass();
        $param->value = ($this->DiscountPercent <= $this->MaxDiscount)?'Да':'Нет';
        $param->formula = "$this->DiscountPercent% <= $this->MaxDiscount%";
        return $param;
    }

    public function getParam2Attribute()
    {
        $param = new \stdClass();
        $param->value = ($this->DiscountPercent <= ($this->MaxDiscount + 5))?'Да':'Нет';
        $param->formula = "$this->DiscountPercent% <= ($this->MaxDiscount + 5)%";
        return $param;
    }

    public function getParam3Attribute()
    {
        $param = new \stdClass();
        $param->value = ($this->MaxDiscount > 11)?'Да':'Нет';
        $param->formula = "$this->MaxDiscount% > 11%";
        return $param;
    }

    public function getProductShopPriceAttribute()
    {
        $value = 0;
        if($this->product and $vPrice = $this->product->priceByUnloadingOption($this->shop_id))
            $value = $vPrice;
        return $value;
    }

    public function getWBTotalDiscountAttribute()
    {
        $param = new \stdClass();
        $param->value = '';
        $param->formula = '';

        if($Price = $this->product?Wildberries::getPrice($this->product, $this->shop_id):false)
        {
            $param->value = ceil(100 - ($this->planned_price / $Price->price) * 100 );
            $param->formula = "Округлить до большего (100 - ($this->planned_price / $Price->price) * 100 ) %";
        }

        return $param;
    }





}
