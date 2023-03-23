<?php

namespace App\Eloquent\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesFinancesFromAPI extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price' => 'float',
    ];

    public function service()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesFinancesService', 'id', 'service_id');
    }

    public function sale()
    {
        return $this->hasOne('App\Eloquent\Sales\Sale', 'id', 'sale_id');
    }

}
