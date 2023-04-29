<?php
declare(strict_types=1);

namespace App\Models\Others\Excel\ZipStream\Option;

use App\Models\Others\Excel\PhpEnum\Enum;

/**
 * Methods enum
 *
 * @method static STORE(): Method
 * @method static DEFLATE(): Method
 * @psalm-immutable
 */
class Method extends Enum
{
    const STORE = 0x00;
    const DEFLATE = 0x08;
}
