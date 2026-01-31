<?php declare(strict_types=1);

namespace App\Database\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use UnexpectedValueException;

final class IpAddressType extends Type
{
    public const string NAME = 'ip_address';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'INET';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_IP)) {
            throw new UnexpectedValueException('Invalid IP address returned from database.');
        }

        return $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!filter_var($value, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("Invalid IP Address: $value");
        }

        return (string) $value;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return false;
    }
}