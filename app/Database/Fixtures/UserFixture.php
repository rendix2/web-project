<?php declare(strict_types=1);

namespace App\Database\Fixtures;

use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Nette\DI\Container;
use Nette\Security\Passwords;
use Nettrine\Fixtures\Fixture\ContainerAwareInterface;

class UserFixture implements FixtureInterface, OrderedFixtureInterface, ContainerAwareInterface
{

    private Passwords $passwords;

    public function load(ObjectManager $manager) : void
    {
        $password = $this->passwords->hash('secret');

        $userEntity = new UserEntity();
        $userEntity->isActive = true;
        $userEntity->name = 'TestName';
        $userEntity->surname = 'TestSurname';
        $userEntity->username = 'TestUsername';
        $userEntity->email = 'test@test.test';
        $userEntity->password = $password;

        $userPasswordEntity = new UserPasswordEntity();
        $userPasswordEntity->password = $password;
        $userPasswordEntity->user = $userEntity;

        $userEmailEntity = new UserEmailEntity();
        $userEmailEntity->email = 'test@test.test';
        $userEmailEntity->user = $userEntity;

        $adminRoleEntity = $manager->getRepository(RoleEntity::class)
            ->findOneBy(
                [
                    'name' => 'Admin',
                ]
            );

        $userEntity->addRoleEntity($adminRoleEntity);
        $userEntity->addUserPasswordEntity($userPasswordEntity);
        $userEntity->addUserEmailEntity($userEmailEntity);

        $manager->persist($userEntity);
        $manager->flush();
    }

    public function getOrder() : int
    {
        return 10;
    }

    public function setContainer(Container $container) : void
    {
        $this->passwords = $container->getByType(Passwords::class);
    }

}
