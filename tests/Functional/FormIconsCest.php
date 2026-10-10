<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Tests\Support\FunctionalTester;

/**
 * The inputs of the forms show an icon in front of them (a Bootstrap 5 input group, see IconBootstrapRenderer): `.input-group` >
 * `span.input-group-text` > `i.fa-xxx` followed by the control of the given name. A submit is a `<button>` with the icon before the
 * caption. The username, e-mail and password controls (own classes in `app/Forms`) are covered as well.
 */
class FormIconsCest
{
    public function theHomePageMentionsTheRenderer(FunctionalTester $I) : void
    {
        $I->amOnPage('/');

        $I->see('IconBootstrapRenderer');
        $I->see('input group', 'li');
    }

    public function loginForm(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.login/');

        $this->seeIcon($I, 'username', 'fa-user', 'input');
        $this->seeIcon($I, 'password', 'fa-lock', 'input');
        $this->seeSubmitIcon($I, 'login', 'fa-sign-in-alt');
        $I->dontSeeElement('//input[@name="stayLoggedIn"]/ancestor::div[contains(@class, "input-group")]');
    }

    public function registrationForm(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.registration/');

        $this->seeIcon($I, 'name', 'fa-id-card', 'input');
        $this->seeIcon($I, 'surname', 'fa-id-card', 'input');
        $this->seeIcon($I, 'username', 'fa-user', 'input');
        $this->seeIcon($I, 'email', 'fa-at', 'input');
        $this->seeIcon($I, 'password', 'fa-lock', 'input');
        $this->seeIcon($I, 'password2', 'fa-lock', 'input');
        $this->seeSubmitIcon($I, 'send', 'fa-user-plus');
    }

    public function forgetPasswordRequestForm(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.forget-password.request/');

        $this->seeIcon($I, 'email', 'fa-at', 'input');
        $this->seeSubmitIcon($I, 'request', 'fa-paper-plane');
    }

    public function userAccountForms(FunctionalTester $I) : void
    {
        $this->login($I);

        $I->amOnPage('/web.user.edit/');
        $this->seeIcon($I, 'name', 'fa-id-card', 'input');
        $this->seeIcon($I, 'surname', 'fa-id-card', 'input');
        $this->seeSubmitIcon($I, 'send', 'fa-save');

        $I->amOnPage('/web.user.change-password/');
        $this->seeIcon($I, 'currentPassword', 'fa-lock', 'input');
        $this->seeIcon($I, 'password', 'fa-lock', 'input');
        $this->seeIcon($I, 'password2', 'fa-lock', 'input');
        $this->seeSubmitIcon($I, 'send', 'fa-key');

        $I->amOnPage('/web.user.change-email/');
        $this->seeIcon($I, 'currentPassword', 'fa-lock', 'input');
        $this->seeIcon($I, 'email', 'fa-at', 'input');
        $this->seeSubmitIcon($I, 'send', 'fa-save');
    }

    public function adminUserForms(FunctionalTester $I) : void
    {
        $this->login($I);

        $I->amOnPage('/admin.user.create/');
        $this->seeIcon($I, 'name', 'fa-id-card', 'input');
        $this->seeIcon($I, 'username', 'fa-user', 'input');
        $this->seeIcon($I, 'email', 'fa-at', 'input');
        $this->seeIcon($I, 'password', 'fa-lock', 'input');
        $this->seeIcon($I, 'password2', 'fa-lock', 'input');
        $this->seeSubmitIcon($I, 'send', 'fa-plus');

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $user = $em->getRepository(UserEntity::class)->findOneBy(['username' => 'TestUsername']);
        $I->assertNotNull($user, 'The TestUsername user is missing in the database.');

        $I->amOnPage('/admin.user.edit/default/' . $user->uuid);
        $this->seeIcon($I, 'username', 'fa-user', 'input');
        $this->seeIcon($I, 'email', 'fa-at', 'input');
        $this->seeSubmitIcon($I, 'send', 'fa-save');
        $I->dontSeeElement('//input[@name="isActive"]/ancestor::div[contains(@class, "input-group")]');
    }

    public function adminRoleForms(FunctionalTester $I) : void
    {
        $this->login($I);

        $I->amOnPage('/admin.role.create/');
        $this->seeIcon($I, 'name', 'fa-user-tag', 'input');
        $this->seeSubmitIcon($I, 'send', 'fa-plus');

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $role = $em->getRepository(RoleEntity::class)->findOneBy([], ['id' => 'ASC']);
        $I->assertNotNull($role, 'No role in the database.');

        $I->amOnPage('/admin.role.edit/default/' . $role->id);
        $this->seeIcon($I, 'name', 'fa-user-tag', 'input');
        $this->seeSubmitIcon($I, 'send', 'fa-save');
    }

    private function login(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => 'TestUsername', 'password' => 'secret']);
        $I->seeResponseCodeIs(200);
    }

    private function seeSubmitIcon(FunctionalTester $I, string $name, string $icon) : void
    {
        $I->seeElement(sprintf(
            '//button[@type="submit" and @name="%s"]/i[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
            $name,
            $icon
        ));
    }

    private function seeIcon(FunctionalTester $I, string $name, string $icon, string $element) : void
    {
        $I->seeElement(sprintf(
            '//div[contains(@class, "input-group")]/span[@class="input-group-text"]/i[contains(concat(" ", normalize-space(@class), " "), " %s ")]/parent::span/following-sibling::%s[@name="%s"]',
            $icon,
            $element,
            $name
        ));
    }

}
