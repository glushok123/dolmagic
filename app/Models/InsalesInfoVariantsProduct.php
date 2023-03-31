<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsalesInfoVariantsProduct extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'insales_info_variants_products';

    /**
     * @var array
     */
    protected $fillable = [
        'variants_id_insales',
        'sku',
        'price',
        'old_price',
        'variants_created_at',
        'variants_updated_at',
        'insales_info_products_id',
    ];
}
