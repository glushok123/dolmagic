<?php

namespace App\Models\Others\Excel\PhpSpreadsheet\Calculation\Statistical;

use App\Models\Others\Excel\PhpSpreadsheet\Calculation\Exception;
use App\Models\Others\Excel\PhpSpreadsheet\Calculation\Functions;
use App\Models\Others\Excel\PhpSpreadsheet\Calculation\MathTrig;
use App\Models\Others\Excel\PhpSpreadsheet\Shared\IntOrFloat;

class Permutations
{
    /**
     * PERMUT.
     *
     * Returns the number of permutations for a given number of objects that can be
     *        selected from number objects. A permutation is any set or subset of objects or
     *        events where internal order is significant. Permutations are different from
     *        combinations, for which the internal order is not significant. Use this function
     *        for lottery-style probability calculations.
     *
     * @param mixed $numObjs Integer number of different objects
     * @param mixed $numInSet Integer number of objects in each permutation
     *
     * @return float|int|string Number of permutations, or a string containing an error
     */
    public static function PERMUT($numObjs, $numInSet)
    {
        $numObjs = Functions::flattenSingleValue($numObjs);
        $numInSet = Functions::flattenSingleValue($numInSet);

        try {
            $numObjs = StatisticalValidations::validateInt($numObjs);
            $numInSet = StatisticalValidations::validateInt($numInSet);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        if ($numObjs < $numInSet) {
            return Functions::NAN();
        }
        $result = round(MathTrig\Factorial::fact($numObjs) / MathTrig\Factorial::fact($numObjs - $numInSet));

        return IntOrFloat::evaluate($result);
    }

    /**
     * PERMUTATIONA.
     *
     * Returns the number of permutations for a given number of objects (with repetitions)
     *     that can be selected from the total objects.
     *
     * @param mixed $numObjs Integer number of different objects
     * @param mixed $numInSet Integer number of objects in each permutation
     *
     * @return float|int|string Number of permutations, or a string containing an error
     */
    public static function PERMUTATIONA($numObjs, $numInSet)
    {
        $numObjs = Functions::flattenSingleValue($numObjs);
        $numInSet = Functions::flattenSingleValue($numInSet);

        try {
            $numObjs = StatisticalValidations::validateInt($numObjs);
            $numInSet = StatisticalValidations::validateInt($numInSet);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        if ($numObjs < 0 || $numInSet < 0) {
            return Functions::NAN();
        }

        $result = $numObjs ** $numInSet;

        return IntOrFloat::evaluate($result);
    }
}
