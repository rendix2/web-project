<?php declare(strict_types=1);

namespace App\Database\Fixtures;

use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Nette\DI\Container;
use Nette\Security\Passwords;
use Nettrine\Fixtures\ContainerAwareInterface;

class UserFixture implements FixtureInterface, OrderedFixtureInterface, ContainerAwareInterface
{

    private Passwords $passwords;

    public function load(ObjectManager $manager)
    {
        $userEntity = new UserEntity();
        $userEntity->isActive = true;
        $userEntity->name = 'Test';
        $userEntity->surname = 'Test';
        $userEntity->username = 'Test';
        $userEntity->email = 'test@test.test';
        $userEntity->password = $this->passwords->hash('secret');

        $userPasswordEntity = new UserPasswordEntity();
        $userPasswordEntity->password = $this->passwords->hash('secret');
        $userPasswordEntity->user = $userEntity;

        $userEntity->addUserPassword($userPasswordEntity);

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
