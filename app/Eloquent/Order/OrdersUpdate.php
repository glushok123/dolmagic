<?php

namespace App\Eloquent\Order;

use Illuminate\Database\Eloquent\Model;

class OrdersUpdate extends Model
{
    public function type()
    {
        return $this->hasOne('App\Eloquent\Order\OrdersUpdatesType', 'id', 'type_id');
    }

    public function user()
    {
        return $this->hasOne('App\User', 'id', 'user_id');
    }

    // Mutators
    public function getClassAttribute()
    {
        $statusClass = '';
        switch($this->attributes['movement_state']){
            case -1:
                $statusClass = 'table-danger';
                break;
            case 0:
                $statusClass = 'table-warning';
                break;
            case 1:
                $statusClass = 'table-success';
                break;
        };
        return $statusClass;
    }

}
