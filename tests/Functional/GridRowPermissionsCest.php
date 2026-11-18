<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserEntity;
use Codeception\Attribute\Group;
use Nette\Security\Passwords;
use Tests\Support\FunctionalTester;

/**
 * `allowRowsAction()` / `allowRowsInlineEdit()` of the datagrid only decide what the page SHOWS: the signal handlers of the datagrid
 * (`handleActionCallback`, the inline edit submit) never ask them again. The admin user grid hides "delete" on the admin's own row,
 * yet the hand made request `?do=grid-actionCallback&grid-__key=delete&grid-__id=<own uuid>` deleted the account. The grids are
 * `SecuredDatagrid`s (App\UI\Grid), which ask the very same condition on the server before any handler runs. Every "forbidden"
 * case has a twin that is allowed, so the hand made request is known to be well formed.
 */
#[Group('grid')]
class GridRowPermissionsCest
{
    public function _before(FunctionalTester $I) : void
    {
        $I->loginAs(FunctionalTester::ROLE_ADMIN);
    }

    public function anAdminCannotDeleteHimselfBySignal(FunctionalTester $I) : void
    {
        $own = $this->em($I)->getRepository(UserEntity::class)->findOneBy(['username' => 'TestUsername']);

        if ($own === null) {
            $I->fail('The fixture user TestUsername is missing.');
        }

        $I->amOnPage($this->deleteUrl('delete', (string) $own->uuid));

        $I->seeRefused(403, 'delete own account');
        $I->assertTrue($this->userExists($I, (string) $own->uuid), 'The admin deleted his own account by a hand made request.');
    }

    public function anAdminDeletesAnotherUserBySignal(FunctionalTester $I) : void
    {
        $other = $this->createUser($I);

        $I->amOnPage($this->deleteUrl('delete', (string) $other->uuid));

        $I->dontSeeInSource('id="tracy-bs"');
        $I->assertFalse($this->userExists($I, (string) $other->uuid), 'The hand made delete request does not work at all (the test request is wrong).');
    }

    public function anUnknownOrALinkActionIsNotFound(FunctionalTester $I) : void
    {
        $other = $this->createUser($I);

        // "edit" is a link, no callback; "nope" does not exist: the datagrid threw DatagridException (a 500 in production)
        foreach (['nope', 'edit'] as $action) {
            $I->amOnPage($this->deleteUrl($action, (string) $other->uuid));
            $I->seeRefused(404, 'action ' . $action);
        }

        $I->assertTrue($this->userExists($I, (string) $other->uuid));
    }

    private function deleteUrl(string $action, string $id) : string
    {
        return '/admin.user.list/?' . http_build_query(['grid-__id' => $id, 'grid-__key' => $action, 'do' => 'grid-actionCallback']);
    }


    private function userExists(FunctionalTester $I, string $uuid) : bool
    {
        $em = $this->em($I);
        $em->clear();

        return $em->getRepository(UserEntity::class)->findOneBy(['uuid' => $uuid]) !== null;
    }

    private function createUser(FunctionalTester $I) : UserEntity
    {
        $tag = 'gridperm' . random_int(100000, 999999);
        $em = $this->em($I);

        $user = new UserEntity();
        $user->name = 'Grid';
        $user->surname = 'Permissions';
        $user->username = $tag;
        $user->email = $tag . '@example.com';
        $user->password = $I->grabService(Passwords::class)->hash('Abcdefgh1234!x');
        $user->isActive = true;

        $em->persist($user);
        $em->flush();
        $I->trackCreatedEntity(UserEntity::class, $user->id);

        return $user;
    }

    private function em(FunctionalTester $I) : EntityManagerDecorator
    {
        return $I->grabService(EntityManagerDecorator::class);
    }
}
