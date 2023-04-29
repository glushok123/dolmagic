<?php

namespace App\Eloquent\Shops;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ShopPrice extends Model
{
    protected $casts = [
        'price' => 'float',
        'old_price' => 'float',
    ];

    protected $guarded = [];

    public function getUpdatedDateTimeAttribute(): string
    {
        return Carbon::parse($this->updated_at)->setTimezone('Europe/Moscow')->toDateTimeString();
    }
}
