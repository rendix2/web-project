<?php declare(strict_types=1);

namespace App\PHPStan\Doctrine\Types;

use PHPStan\Type\Type;
use PHPStan\Type\StringType;
use PHPStan\Type\NullType;
use PHPStan\Type\UnionType;

class IpAddressTypeDescriptor implements \PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor
{

    #[\Override]
    public function getType() : string
    {
        return \App\Database\Types\IpAddressType::class;
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