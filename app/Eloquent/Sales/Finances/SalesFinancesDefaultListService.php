<?php

namespace App\Eloquent\Sales\Finances;

use Illuminate\Database\Eloquent\Model;

class SalesFinancesDefaultListService extends Model
{
    protected $guarded = [];

    public function service()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesFinancesService', 'id', 'service_id');
    }

    public function cost()
    {
        return $this->hasOne('App\Eloquent\Directories\Cost', 'id', 'cost_id');
    }
}
