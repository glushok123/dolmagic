<?php
declare(strict_types=1);

namespace App\Models\Others\Excel\ZipStream\Exception;

use App\Models\Others\Excel\ZipStream\Exception;

/**
 * This Exception gets invoked if a counter value exceeds storage size
 */
class OverflowException extends Exception
{
    public function __construct()
    {
        parent::__construct('File size exceeds limit of 32 bit integer. Please enable "zip64" option.');
    }
}
