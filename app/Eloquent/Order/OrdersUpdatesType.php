<?php

namespace App\Eloquent\Order;

use Illuminate\Database\Eloquent\Model;

class OrdersUpdatesType extends Model
{
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
