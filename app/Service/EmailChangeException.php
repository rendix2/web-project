<?php declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/**
 * A change of the e-mail that cannot be confirmed. The reason is a translation key suffix: `invalid` (unknown, used or expired link)
 * or `taken` (the address belongs to another account now).
 */
final class EmailChangeException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
    ) {
        parent::__construct('E-mail change refused: ' . $reason);
    }

}
