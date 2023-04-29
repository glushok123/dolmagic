<?php

namespace App\Models\Others\Excel\PhpSpreadsheet\Calculation\MathTrig;

use App\Models\Others\Excel\PhpSpreadsheet\Calculation\Exception;

class Exp
{
    /**
     * EXP.
     *
     * Returns the result of builtin function exp after validating args.
     *
     * @param mixed $number Should be numeric
     *
     * @return float|string Rounded number
     */
    public static function evaluate($number)
    {
        try {
            $number = Helpers::validateNumericNullBool($number);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return exp($number);
    }
}
