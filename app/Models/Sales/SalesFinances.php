<?php

namespace App\Models\Sales;


use App\Eloquent\Sales\SalesFinancesFromFile;
use App\Models\Model;

class SalesFinances extends Model{


    public static function getSaleCostFromFile($Sale)
    {
        return SalesFinancesFromFile::where([
            ['sale_id', $Sale->id],
        ])->whereHas('service', function($q)
        {
            $q->whereNotNull('cost_id');
        })->get();
    }

    public static function getSaleIncomeFromFileTotal($Sale)
    {
        $total = SalesFinancesFromFile::where([
            ['sale_id', $Sale->id],
        ])->whereHas('service', function($q)
        {
            $q->whereNull('cost_id');
        })->sum('price');

        $total = (float) $total;

        return $total;
    }

    public static function checkAndSaveSaleReconciled($Sale)
    {
        $countNotReconciled = SalesFinancesFromFile::where([
            ['sale_id', $Sale->id],
            ['reconciled', '!=', 1],
        ])->whereHas('service', function($q)
        {
            $q->whereNotNull('cost_id');
        })->count();

        $Sale->finances_reconciled = !($countNotReconciled > 0);
        $Sale->save();
    }

}


