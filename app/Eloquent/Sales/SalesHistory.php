<?php

namespace App\Eloquent\Sales;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SalesHistory extends Model
{

    public function type()
    {
        return $this->hasOne('App\Eloquent\Sales\SalesHistoriesType', 'id', 'type_id');
    }

    public function user()
    {
        return $this->hasOne('App\User', 'id', 'user_id');
    }

}
