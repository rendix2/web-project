<?php

namespace Database\Fixtures;


use App\Model\Entity\UserEntity;
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
