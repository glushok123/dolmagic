<?php

namespace App\Models\Others\Excel\PhpSpreadsheet\Collection;

use App\Models\Others\Excel\PhpSpreadsheet\Settings;
use App\Models\Others\Excel\PhpSpreadsheet\Worksheet\Worksheet;

abstract class CellsFactory
{
    /**
     * Initialise the cache storage.
     *
     * @param Worksheet $worksheet Enable cell caching for this worksheet
     *
     * @return Cells
     * */
    public static function getInstance(Worksheet $worksheet)
    {
        return new Cells($worksheet, Settings::getCache());
    }
}
