<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Nette\Security\Passwords;
use Tests\Support\FunctionalTester;

/**
 * Admin section: role/user management. Covers the bug found 2026-09-28 in
 * RoleEntity::$users / UserEntity::$roles - both sides of that ManyToMany carried
 * cascade: ['persist', 'remove'], which for a many-to-many means Doctrine cascades an actual
 * ENTITY remove, not just a join-table row delete. Removing one user therefore deleted every
 * RoleEntity that user had, which in turn deleted every OTHER user who also had that role -
 * deleting a single user (or a role, from the Admin:Role:List grid) could wipe out arbitrary
 * unrelated accounts, including other Admins.
 */
class AdminCest
{
    public function deletingAUserDoesNotCascadeDeleteASharedRole(FunctionalTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $role = $this->createRole($I, $em);
        $userA = $this->createUser($I, $em, 'cascadeA');
        $userB = $this->createUser($I, $em, 'cascadeB');

        $userA->addRoleEntity($role);
        $userB->addRoleEntity($role);
        $em->persist($userA);
        $em->persist($userB);
        $em->flush();

        // Capture plain scalar ids before the risky operation - if cascade: 'remove' really does
        // cascade-delete $role, Doctrine unsets its identifier on the in-memory object post-flush,
        // so reading $role->id AFTER the remove()+flush() below would itself throw/lie.
        $roleId = $role->id;
        $userBId = $userB->id;

        $em->remove($userA);
        $em->flush();
        $em->clear();

        $I->assertNotNull(
            $em->getRepository(RoleEntity::class)->find($roleId),
            'Deleting a user cascade-deleted a RoleEntity it merely had assigned.'
        );
        $I->assertNotNull(
            $em->getRepository(UserEntity::class)->find($userBId),
            'Deleting one user cascade-deleted another, unrelated user who shared a role with it.'
        );
    }

    public function deletingARoleDoesNotCascadeDeleteItsUsers(FunctionalTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $role = $this->createRole($I, $em);
        $userA = $this->createUser($I, $em, 'roleDeleteA');

        $userA->addRoleEntity($role);
        $em->persist($userA);
        $em->flush();

        $userAId = $userA->id;

        $em->remove($role);
        $em->flush();
        $em->clear();

        $I->assertNotNull(
            $em->getRepository(UserEntity::class)->find($userAId),
            'Deleting a role cascade-deleted every user that had it assigned.'
        );
    }

    private function createRole(FunctionalTester $I, EntityManagerDecorator $em) : RoleEntity
    {
        $role = new RoleEntity();
        $role->name = 'CascadeTestRole' . random_int(100000, 999999);
        $role->description = 'test';

        $em->persist($role);
        $em->flush();

        return $role;
    }

    private function createUser(FunctionalTester $I, EntityManagerDecorator $em, string $label) : UserEntity
    {
        $tag = $label . random_int(100000, 999999);
        $passwords = $I->grabService(Passwords::class);

        $userEntity = new UserEntity();
        $userEntity->name = 'Cascade';
        $userEntity->surname = 'Test';
        $userEntity->username = $tag;
        $userEntity->email = $tag . '@example.com';
        $userEntity->password = $passwords->hash('Abcdefgh1234!x');
        $userEntity->isActive = true;

        $em->persist($userEntity);
        $em->flush();
        $I->trackCreatedEntity(UserEntity::class, $userEntity->id);

        return $userEntity;
    }
}
