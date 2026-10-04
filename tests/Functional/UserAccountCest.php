<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserActivationEntity;
use App\Model\Entity\UserEntity;
use Nette\Security\Passwords;
use Tests\Support\FunctionalTester;

/**
 * Account activation and the change-password/change-email forms. Covers the bugs found/fixed
 * 2026-09-29: ChangePasswordPresenter checked isLoggedIn() only in renderDefault(), which Nette
 * calls AFTER form-signal handling (Presenter::run() processes the signal, i.e. the form's
 * onSuccess, before rendering) - the check never actually blocked a direct form submission, it
 * only skipped showing the page. Also: account activation showed the literal untranslated string
 * "success" as its flash message (no translation key ever existed for it).
 */
class UserAccountCest
{
    private const PASSWORD = 'Abcdefgh1234!x';

    /**
     * Debug mode is always on in this project (see AGENTS.md "Known caveats"), so Tracy's
     * bluescreen reports every BadRequestException as HTTP 500 regardless of the code passed
     * to $this->error() - the intended 403 only shows up in the page title.
     */
    public function changingPasswordWithoutBeingLoggedInIsRejected(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.change-password/');
        $I->seeResponseCodeIs(500);
        $I->see('403');
    }

    public function changingEmailWithoutBeingLoggedInIsRejected(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.change-email/');
        $I->seeResponseCodeIs(500);
        $I->see('403');
    }

    public function activatingWithAValidKeyActivatesTheAccountAndShowsTheTranslatedMessage(FunctionalTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $passwords = $I->grabService(Passwords::class);

        $tag = 'activate' . random_int(100000, 999999);

        $userEntity = new UserEntity();
        $userEntity->name = 'Activation';
        $userEntity->surname = 'Test';
        $userEntity->username = $tag;
        $userEntity->email = $tag . '@example.com';
        $userEntity->password = $passwords->hash(self::PASSWORD);
        $userEntity->isActive = false;

        $em->persist($userEntity);
        $em->flush();
        $I->trackCreatedEntity(UserEntity::class, $userEntity->id);

        $activationEntity = new UserActivationEntity();
        $activationEntity->user = $userEntity;
        $activationEntity->activationKey = 'functional-test-activation-key-' . $tag;

        $em->persist($activationEntity);
        $em->flush();

        $I->amOnPage('/web.user.activation/?userId=' . $userEntity->id . '&key=' . $activationEntity->activationKey);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->see('byl úspěšně aktivován');

        $em->clear();
        $I->assertTrue((bool) $em->getRepository(UserEntity::class)->find($userEntity->id)?->isActive);
        $I->assertNull($em->getRepository(UserActivationEntity::class)->find($activationEntity->id));
    }
}
