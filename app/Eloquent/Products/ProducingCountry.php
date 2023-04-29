<?php

namespace App\Eloquent\Products;

use Illuminate\Database\Eloquent\Model;

class ProducingCountry extends Model
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
        return route('products.producing_countries.edit', ['id' => $this->id]);
    }
}
