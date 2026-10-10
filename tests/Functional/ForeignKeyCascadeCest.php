<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserActivationEntity;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEmailChangeRequestEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use App\Model\Entity\UserPasswordRequestEntity;
use Tests\Support\FunctionalTester;

/**
 * Everything that belongs to a user goes with him: the foreign keys of the user tables are ON DELETE CASCADE, so even a plain
 * `DELETE FROM users` (not only the ORM) leaves no orphan. The role stays, only the link user_role goes.
 */
class ForeignKeyCascadeCest
{
    public function deletingAUserRemovesEverythingThatBelongsToHim(FunctionalTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $tag = 'cascade' . random_int(100000, 999999);

        $user = new UserEntity();
        $user->name = 'Cascade';
        $user->surname = 'Test';
        $user->username = $tag;
        $user->email = $tag . '@example.com';
        $user->password = 'x';
        $user->isActive = true;

        $password = new UserPasswordEntity();
        $password->user = $user;
        $password->password = 'x';
        $user->addUserPasswordEntity($password);

        $email = new UserEmailEntity();
        $email->user = $user;
        $email->email = $user->email;
        $user->addUserEmailEntity($email);

        $activation = new UserActivationEntity();
        $activation->user = $user;
        $activation->activationKey = $tag;
        $user->addUserActivationEntity($activation);

        $autoLogin = new UserAutoLoginEntity();
        $autoLogin->user = $user;
        $autoLogin->tokenHash = hash('sha256', $tag);
        $autoLogin->ipAddress = '10.0.0.1';
        $user->addAutoLoginEntity($autoLogin);

        $passwordRequest = new UserPasswordRequestEntity();
        $passwordRequest->user = $user;
        $passwordRequest->forgetKey = $tag;
        $user->addUserPasswordRequestEntity($passwordRequest);

        $changeRequest = new UserEmailChangeRequestEntity();
        $changeRequest->user = $user;
        $changeRequest->newEmail = 'new.' . $user->email;
        $changeRequest->tokenHash = hash('sha256', 'change' . $tag);

        $role = new RoleEntity();
        $role->name = $tag;
        $role->description = 'Cascade test';
        $user->addRoleEntity($role);

        $em->persist($user);
        $em->persist($changeRequest);
        $em->persist($role);
        $em->flush();

        $userId = $user->id;
        $roleId = $role->id;

        $connection = $em->getConnection();

        foreach (['user_password', 'user_email', 'user_activation', 'user_auto_login', 'user_password_request', 'user_email_change_request', 'user_role'] as $table) {
            $I->assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM ' . $table . ' WHERE user_id = ?', [$userId]), 'The test data are missing in ' . $table);
        }

        // plain SQL: the database does it, not the ORM
        $connection->executeStatement('DELETE FROM users WHERE id = ?', [$userId]);

        foreach (['user_password', 'user_email', 'user_activation', 'user_auto_login', 'user_password_request', 'user_email_change_request', 'user_role'] as $table) {
            $I->assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM ' . $table . ' WHERE user_id = ?', [$userId]), $table . ' keeps rows of a deleted user.');
        }

        $I->assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM role WHERE id = ?', [$roleId]), 'Deleting a user deleted the role.');

        $connection->executeStatement('DELETE FROM role WHERE id = ?', [$roleId]);
    }

}
