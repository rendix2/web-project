<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Tests\Support\FunctionalTester;

/**
 * The navigation of the admin is written by hand in `UI/Admin/@layout.latte` (no menu package): the section of the current page
 * is the `active` link of the navbar (`aria-current="page"`) and the first item of the breadcrumb, translated.
 */
class AdminNavigationCest
{
    private const USERS = '//nav[@id="admin-navigation"]//a[contains(@class, "nav-link")][contains(@href, "/admin.user.list/")]';

    private const ROLES = '//nav[@id="admin-navigation"]//a[contains(@class, "nav-link")][contains(@href, "/admin.role.list/")]';

    public function usersSectionIsActiveOnTheUserList(FunctionalTester $I) : void
    {
        $this->login($I);
        $I->amOnPage('/admin.user.list/');

        $I->seeElement(self::USERS . '[contains(concat(" ", normalize-space(@class), " "), " active ")][@aria-current="page"]');
        $I->dontSeeElement(self::ROLES . '[contains(concat(" ", normalize-space(@class), " "), " active ")]');
        $I->see('Uživatelé', self::USERS);
        $I->see('Role', self::ROLES);
    }

    public function rolesSectionIsActiveOnTheRoleList(FunctionalTester $I) : void
    {
        $this->login($I);
        $I->amOnPage('/admin.role.list/');

        $I->seeElement(self::ROLES . '[contains(concat(" ", normalize-space(@class), " "), " active ")][@aria-current="page"]');
        $I->dontSeeElement(self::USERS . '[contains(concat(" ", normalize-space(@class), " "), " active ")]');
    }

    public function usersSectionStaysActiveOnTheEditAndCreatePages(FunctionalTester $I) : void
    {
        $this->login($I);

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $user = $em->getRepository(UserEntity::class)->findOneBy(['username' => 'TestUsername']);
        $I->assertNotNull($user);

        foreach (['/admin.user.edit/default/' . $user->uuid, '/admin.user.create/'] as $url) {
            $I->amOnPage($url);
            $I->seeElement(self::USERS . '[contains(concat(" ", normalize-space(@class), " "), " active ")]');
            $I->dontSeeElement(self::ROLES . '[contains(concat(" ", normalize-space(@class), " "), " active ")]');
        }
    }

    public function rolesSectionStaysActiveOnTheEditAndCreatePages(FunctionalTester $I) : void
    {
        $this->login($I);

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $role = $em->getRepository(RoleEntity::class)->findOneBy([], ['id' => 'ASC']);
        $I->assertNotNull($role);

        foreach (['/admin.role.edit/default/' . $role->id, '/admin.role.create/'] as $url) {
            $I->amOnPage($url);
            $I->seeElement(self::ROLES . '[contains(concat(" ", normalize-space(@class), " "), " active ")]');
            $I->dontSeeElement(self::USERS . '[contains(concat(" ", normalize-space(@class), " "), " active ")]');
        }
    }

    public function breadcrumbLeadsBackToTheSection(FunctionalTester $I) : void
    {
        $this->login($I);
        $I->amOnPage('/admin.user.create/');

        $I->seeElement('//nav[@aria-label="breadcrumb"]//li[contains(@class, "breadcrumb-item")]/a[contains(@href, "/admin.user.list/")]');
        $I->seeElement('//nav[@aria-label="breadcrumb"]//li[contains(@class, "active")][@aria-current="page"]');
    }

    public function navigationIsNotRenderedByAMenuComponent(FunctionalTester $I) : void
    {
        $this->login($I);
        $I->amOnPage('/admin.user.list/');

        // the markup of the old package template put `active` outside of the class attribute
        $I->dontSeeInSource('class="nav-link" active');
    }

    private function login(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => 'TestUsername', 'password' => 'secret']);
        $I->seeResponseCodeIs(200);
    }

}
