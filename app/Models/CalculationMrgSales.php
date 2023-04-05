<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalculationMrgSales extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'calculation_mrg_sales';

    /**
     * @var array
     */
    protected $fillable = [
        'sale_id',
        'mrg_sale',
    ];
}
