<?php

namespace App\Models\Others\Excel\PhpSpreadsheet\Calculation\MathTrig;

use App\Models\Others\Excel\PhpSpreadsheet\Calculation\Exception;

class IntClass
{
    /**
     * INT.
     *
     * Casts a floating point value to an integer
     *
     * Excel Function:
     *        INT(number)
     *
     * @param float $number Number to cast to an integer
     *
     * @return int|string Integer value, or a string containing an error
     */
    public static function evaluate($number)
    {
        try {
            $number = Helpers::validateNumericNullBool($number);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return (int) floor($number);
    }
}
