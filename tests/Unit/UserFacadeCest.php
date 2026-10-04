<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Model\Entity\UserEntity;
use App\Model\Facade\UserFacade;
use App\UI\Web\User\Registration\RegistrationValues;
use App\Database\EntityManagerDecorator;
use Tests\Support\UnitTester;

class UserFacadeCest
{
    public function containerResolvesTheFacade(UnitTester $I) : void
    {
        $I->assertInstanceOf(UserFacade::class, $I->grabService(UserFacade::class));
    }

    public function registerStoresTheTypedValues(UnitTester $I) : void
    {
        $tag = (string) random_int(100000, 999999);

        $values = new RegistrationValues();
        $values->name = 'Unit';
        $values->surname = 'Tester';
        $values->username = 'unittest' . $tag;
        $values->email = 'unittest' . $tag . '@example.com';
        $values->password = 'Abcdefgh1234!x';

        /** @var UserFacade $facade */
        $facade = $I->grabService(UserFacade::class);
        $facade->register($values);

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $em->clear();

        $user = $em->getRepository(UserEntity::class)->findOneBy(['username' => 'unittest' . $tag]);

        $I->assertNotNull($user);
        $I->assertFalse($user->isActive);
        $I->assertSame('Unit', $user->name);
    }
}
