<?php declare(strict_types=1);

namespace App\Model\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use UnexpectedValueException;

final class IpAddressType extends Type
{
    public const string NAME = 'ip_address';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBinaryTypeDeclarationSQL([
            'length' => 16,
            'fixed' => true,
        ]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        $ip = @inet_ntop($value);
        if ($ip === false) {
            throw new UnexpectedValueException('Invalid IP address in database.');
        }

        return $ip;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!filter_var($value, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("Invalid IP Address: $value");
        }

        return inet_pton($value);
    }

}
