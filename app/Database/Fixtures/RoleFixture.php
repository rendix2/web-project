<?php declare(strict_types=1);

namespace App\Database\Fixtures;

use App\Model\Entity\RoleEntity;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class RoleFixture implements FixtureInterface, OrderedFixtureInterface
{

    public function load(ObjectManager $manager) : void
    {
        $roleEntity = new RoleEntity();
        $roleEntity->name = "Normal";
        $roleEntity->description = 'Normal role for normal users';

        $manager->persist($roleEntity);
        $manager->flush();

        $roleEntity = new RoleEntity();
        $roleEntity->name = "Admin";
        $roleEntity->description = 'Admin role. This role can do everything!';

        $manager->persist($roleEntity);
        $manager->flush();
    }

    public function getOrder() : int
    {
        return 0;
    }

}
