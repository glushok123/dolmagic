<?php

namespace App\Eloquent\Products;

use Illuminate\Database\Eloquent\Model;

class ProductCharacter extends Model
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
                $statusClass = ' ';
                //$statusClass = ' table-warning';
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
        return route('products.brands.edit', ['id' => $this->id]);
    }

    public function getProductsCountAttribute(): int
    {
        return Product::where('character_id', $this->id)->count();
    }
}
