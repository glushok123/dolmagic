<?php

namespace App\Eloquent\System;

use App\Models\Systems\Transactions;
use App\Observers\System\SystemTransactionObserver;
use Illuminate\Database\Eloquent\Model;

class SystemsTransactionsFromFile extends Model
{
    protected $casts = [
        'total_amount' => 'float',
        'order_amount' => 'float',
        'commission_amount' => 'float',
    ];

}
