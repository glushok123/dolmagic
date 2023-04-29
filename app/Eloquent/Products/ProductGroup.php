<?php

namespace App\Eloquent\Products;

use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    protected $guarded = array();

    public function getClassAttribute()
    {
        $statusClass = '';
        switch($this->attributes['state']){
            case -1:
                $statusClass = ' table-danger';
                break;
            case 0:
                $statusClass = ' table-warning';
                break;
            case 1:

                break;
            case 2:
                $statusClass = ' table-success';
                break;
        };
        return $statusClass;
    }

    public function getEditPathAttribute()
    {
        return route('products.groups.edit', ['id' => $this->id]);
    }

    public function tmallGroup()
    {
        return $this->hasOne('App\Eloquent\Shops\Products\ShopProductsGroup', 'shop_group_id', 'tmall_group_id');
    }

    public function aliGroup()
    {
        return $this->hasOne('App\Eloquent\Shops\Products\ShopProductsGroup', 'shop_group_id', 'aliexpress_group_id');
    }

    public function getTmallGroupErrorAttribute()
    {
        if(!$this->tmallGroup) return true;
        if(count($this->tmallGroup->childs) > 0) return true;
        return false;
    }

    public function getAlliGroupErrorAttribute()
    {
        if(!$this->aliGroup) return true;
        if(count($this->aliGroup->childs) > 0) return true;
        return false;
    }
}
