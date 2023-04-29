<?php

namespace App\Models\Others\Excel\PhpSpreadsheet\Calculation\LookupRef;

use App\Models\Others\Excel\PhpSpreadsheet\Calculation\Calculation;
use App\Models\Others\Excel\PhpSpreadsheet\Calculation\Functions;
use App\Models\Others\Excel\PhpSpreadsheet\Cell\Cell;

class Formula
{
    /**
     * FORMULATEXT.
     *
     * @param mixed $cellReference The cell to check
     * @param Cell $cell The current cell (containing this formula)
     *
     * @return string
     */
    public static function text($cellReference = '', ?Cell $cell = null)
    {
        if ($cell === null) {
            return Functions::REF();
        }

        preg_match('/^' . Calculation::CALCULATION_REGEXP_CELLREF . '$/i', $cellReference, $matches);

        $cellReference = $matches[6] . $matches[7];
        $worksheetName = trim($matches[3], "'");
        $worksheet = (!empty($worksheetName))
            ? $cell->getWorksheet()->getParent()->getSheetByName($worksheetName)
            : $cell->getWorksheet();

        if (
            $worksheet === null ||
            !$worksheet->cellExists($cellReference) ||
            !$worksheet->getCell($cellReference)->isFormula()
        ) {
            return Functions::NA();
        }

        return $worksheet->getCell($cellReference)->getValue();
    }
}
