<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsalesInfoProduct extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'insales_info_products';

    /**
     * @var array
     */
    protected $fillable = [
        'product_id_insales',
        'product_created_at',
        'product_updated_at',
        'title',
        'short_description',
    ];
}
