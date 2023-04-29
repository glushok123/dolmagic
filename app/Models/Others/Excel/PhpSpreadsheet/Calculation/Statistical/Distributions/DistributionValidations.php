<?php

namespace App\Models\Others\Excel\PhpSpreadsheet\Calculation\Statistical\Distributions;

use App\Models\Others\Excel\PhpSpreadsheet\Calculation\Exception;
use App\Models\Others\Excel\PhpSpreadsheet\Calculation\Functions;
use App\Models\Others\Excel\PhpSpreadsheet\Calculation\Statistical\StatisticalValidations;

class DistributionValidations extends StatisticalValidations
{
    /**
     * @param mixed $probability
     */
    public static function validateProbability($probability): float
    {
        $probability = self::validateFloat($probability);

        if ($probability < 0.0 || $probability > 1.0) {
            throw new Exception(Functions::NAN());
        }

        return $probability;
    }
}
