<?php declare(strict_types=1);

namespace App\PHPStan\Doctrine\Types;

use App\Database\Types\IpAddressType;
use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

class IpAddressTypeDescriptor implements DoctrineTypeDescriptor
{
    #[\Override]
    public function getType() : string
    {
        return IpAddressType::class;
    }

    #[\Override]
    public function getWritableToPropertyType() : Type
    {
        return new StringType();
    }

    #[\Override]
    public function getWritableToDatabaseType() : Type
    {
        return new StringType();
    }

    #[\Override]
    public function getDatabaseInternalType() : Type
    {
        return new StringType();
    }
}
