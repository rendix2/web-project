<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use Tests\Support\FunctionalTester;

/**
 * The admin grids filter text columns with the DQL function REGEXP(column, :pattern) = true. It has to be registered
 * (PostgreSQL has no REGEXP operator) and, like the old (MySQL era) collation the grids were written for, ignore case and accents:
 * "adminrole" finds "ÁdminRole".
 */
class GridFilterCest
{
    public function roleGridFilterIgnoresCaseAndAccents(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => 'TestUsername', 'password' => 'secret']);
        $I->seeResponseCodeIs(200);

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $tag = (string) random_int(100000, 999999);
        $role = new RoleEntity();
        $role->name = 'ÁdminGrid' . $tag;
        $role->description = 'grid filter test';
        $em->persist($role);
        $em->flush();

        $I->haveHttpHeader('Sec-Fetch-Site', 'same-origin');
        $I->haveHttpHeader('X-Requested-With', 'XMLHttpRequest');

        foreach (['admingrid' . $tag, 'ADMINGRID' . $tag, 'ÁdminGrid' . $tag] as $filter) {
            $I->sendAjaxPostRequest('/admin.role.list/?do=grid-filter-submit', [
                'filter' => ['name' => $filter],
                'perPage' => '20',
            ]);
            $I->seeResponseCodeIs(200);
            $I->seeInSource($role->name);
        }

        $I->sendAjaxPostRequest('/admin.role.list/?do=grid-filter-submit', [
            'filter' => ['name' => 'admingrid-not-there' . $tag],
            'perPage' => '20',
        ]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource($role->name);
    }
}
