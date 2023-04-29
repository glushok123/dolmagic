<?php

namespace App\Models\Others\Excel\PhpSpreadsheet\Calculation\Engineering;

use App\Models\Others\Excel\PhpSpreadsheet\Calculation\Exception;
use App\Models\Others\Excel\PhpSpreadsheet\Calculation\Functions;

class EngineeringValidations
{
    /**
     * @param mixed $value
     */
    public static function validateFloat($value): float
    {
        if (!is_numeric($value)) {
            throw new Exception(Functions::VALUE());
        }

        return (float) $value;
    }

    /**
     * @param mixed $value
     */
    public static function validateInt($value): int
    {
        if (!is_numeric($value)) {
            throw new Exception(Functions::VALUE());
        }

        return (int) floor((float) $value);
    }
}
