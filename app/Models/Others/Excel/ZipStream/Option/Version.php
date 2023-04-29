<?php
declare(strict_types=1);

namespace App\Models\Others\Excel\ZipStream\Option;

use App\Models\Others\Excel\PhpEnum\Enum;

/**
 * Class Version
 * @package ZipStream\Option
 *
 * @method static STORE(): Version
 * @method static DEFLATE(): Version
 * @method static ZIP64(): Version
 * @psalm-immutable
 */
class Version extends Enum
{
    const STORE = 0x000A; // 1.00
    const DEFLATE = 0x0014; // 2.00
    const ZIP64 = 0x002D; // 4.50
}
