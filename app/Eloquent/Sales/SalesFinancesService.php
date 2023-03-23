<?php

namespace App\Eloquent\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesFinancesService extends Model
{
    protected $guarded = [];

    public function cost()
    {
        return $this->hasOne('App\Eloquent\Directories\Cost', 'id', 'cost_id');
    }
}
