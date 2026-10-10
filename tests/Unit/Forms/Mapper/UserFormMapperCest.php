<?php declare(strict_types=1);

namespace Tests\Unit\Forms\Mapper;

use App\Forms\Mapper\UserFormMapper;
use App\Model\Entity\UserEntity;
use App\UI\Admin\User\Create\CreateUserValues;
use App\UI\Admin\User\Edit\EditUserValues;
use Tests\Support\Unit\DefaultsContainer;
use Tests\Support\UnitTester;

/**
 * The admin user forms <-> UserEntity: the plain fields only (the password and the e-mail history are domain rules of UserFacade).
 */
class UserFormMapperCest
{
    public function aUserIsFilledButTheHashIsNeverShown(UnitTester $I) : void
    {
        $user = new UserEntity();
        $user->name = 'Eva';
        $user->surname = 'Nováková';
        $user->username = 'eva';
        $user->email = 'eva@example.com';
        $user->password = 'secret-hash';
        $user->isActive = true;

        $container = new DefaultsContainer();
        (new UserFormMapper())->fill($container, $user);

        $I->assertSame([
            'name' => 'Eva',
            'surname' => 'Nováková',
            'username' => 'eva',
            'email' => 'eva@example.com',
            'isActive' => true,
        ], $container->defaults);
    }

    public function anEditWritesThePlainFieldsAndLeavesThePassword(UnitTester $I) : void
    {
        $user = new UserEntity();
        $user->name = 'Old';
        $user->surname = 'Old';
        $user->username = 'old';
        $user->email = 'old@example.com';
        $user->password = 'hash';
        $user->isActive = true;

        $values = new EditUserValues();
        $values->name = 'New';
        $values->surname = 'Person';
        $values->username = 'new';
        $values->email = 'new@example.com';
        $values->password = 'plain-from-the-form';
        $values->isActive = false;

        $I->assertSame($user, (new UserFormMapper())->apply($values, $user));

        $I->assertSame('New', $user->name);
        $I->assertSame('Person', $user->surname);
        $I->assertSame('new', $user->username);
        $I->assertSame('new@example.com', $user->email);
        $I->assertFalse($user->isActive);
        $I->assertSame('hash', $user->password, 'The password is the facade\'s business (hashing, history).');
    }

    public function aCreateBuildsANewUser(UnitTester $I) : void
    {
        $values = new CreateUserValues();
        $values->name = 'Created';
        $values->surname = 'Person';
        $values->username = 'created';
        $values->email = 'created@example.com';
        $values->password = 'plain';
        $values->isActive = true;

        $user = (new UserFormMapper())->apply($values);

        $I->assertSame('Created', $user->name);
        $I->assertSame('created', $user->username);
        $I->assertSame('created@example.com', $user->email);
        $I->assertTrue($user->isActive);
        $I->assertFalse(isset($user->password), 'No hash is made by the mapper.');
    }
}
