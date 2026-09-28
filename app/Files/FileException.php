<?php
declare(strict_types=1);

namespace App\Files;

use RuntimeException;

/** User-facing file manager error (safe to show; never contains server paths). */
final class FileException extends RuntimeException
{
}
