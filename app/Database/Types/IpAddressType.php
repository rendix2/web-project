<?php declare(strict_types=1);

namespace App\Database\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use UnexpectedValueException;

final class IpAddressType extends Type
{
    public const string NAME = 'ip_address';

    /**
     * @param array<string, mixed> $column
     */
    // phpcs:ignore Generic.NamingConventions.CamelCapsFunctionName
    public function getSQLDeclaration(array $column, AbstractPlatform $platform) : string
    {
        return 'INET';
    }

    // phpcs:ignore Generic.NamingConventions.CamelCapsFunctionName
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform) : ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_IP) === false) {
            throw new UnexpectedValueException('Invalid IP address returned from database.');
        }

        return $value;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform) : ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Invalid IP Address.');
        }

        return $value;
    }

    // phpcs:ignore Generic.NamingConventions.CamelCapsFunctionName
    public function requiresSQLCommentHint(AbstractPlatform $platform) : bool
    {
        return false;
    }
}
