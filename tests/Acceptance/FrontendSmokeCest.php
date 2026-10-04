<?php declare(strict_types=1);

namespace Tests\Acceptance;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Tests\Support\AcceptanceTester;

/**
 * Loads the pages in a real browser and fails on any JavaScript error (a broken library after an upgrade, e.g. a
 * Bootstrap 4 plugin under Bootstrap 5, shows up here and nowhere else) or a Tracy screen.
 */
class FrontendSmokeCest
{
    public function pagesLoadWithoutJavaScriptErrors(AcceptanceTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $user = $em->getRepository(UserEntity::class)->findOneBy(['username' => 'TestUsername']);
        $role = $em->getRepository(RoleEntity::class)->findOneBy([]);

        $I->assertNotNull($user, 'The fixture user TestUsername is missing (make db).');
        $I->assertNotNull($role);

        $I->resizeWindow(1400, 1000);

        $problems = [];
        $check = function (string $page) use ($I, &$problems) : void {
            $I->amOnPage($page);
            $I->wait(1);

            $logs = [];
            $I->executeInSelenium(static function ($webDriver) use (&$logs) : void {
                $logs = $webDriver->manage()->getLog('browser');
            });

            foreach ($logs as $entry) {
                if ($entry['level'] === 'SEVERE' && !str_contains($entry['message'], 'favicon')) {
                    $problems[] = $page . ': ' . substr((string) $entry['message'], 0, 300);
                }
            }

            if ($I->executeJS('return document.getElementById("tracy-bs") !== null;') === true) {
                $problems[] = $page . ': Tracy exception screen';
            }
        };

        foreach (['/', '/web.user.login/', '/web.user.registration/', '/web.user.forget-password.request/'] as $page) {
            $check($page);
        }

        $I->amOnPage('/web.user.login/');
        $I->fillField('username', 'TestUsername');
        $I->fillField('password', 'secret');
        $I->click('#frm-loginForm [type=submit]');
        $I->wait(1);

        foreach ([
            '/admin.user.list/', '/admin.user.create/', '/admin.user.edit/default/' . $user->uuid,
            '/admin.role.list/', '/admin.role.create/', '/admin.role.edit/default/' . $role->id,
            '/web.user.view/default/' . $user->uuid, '/web.user.edit/', '/web.user.change-password/', '/web.user.change-email/',
        ] as $page) {
            $check($page);
        }

        $I->assertSame([], $problems, "JavaScript errors:\n" . implode("\n", $problems));
    }
}
