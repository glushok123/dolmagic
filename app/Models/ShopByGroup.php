<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopByGroup extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'shop_by_groups';

    /**
     * @var array
     */
    protected $fillable = [
        'group_id',
        'shop_id',
    ];
}
