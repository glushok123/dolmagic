<?php

namespace App\Eloquent\Warehouse;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class WarehousesRemoteStock extends Model
{
    public function getCreatedDatetimeAttribute()
    {
        return Carbon::parse($this->created_at)->setTimezone('Europe/Moscow')->toDateTimeString();
    }
}
