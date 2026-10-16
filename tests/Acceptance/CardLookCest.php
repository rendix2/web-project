<?php declare(strict_types=1);

namespace Tests\Acceptance;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Every card looks the same: the same corners and a shadow (one `.card` rule in assets/styles.css; a card carries no `shadow*` or
 * `rounded*` class of its own and a `.card-group` is a row of SEPARATE cards, not one glued block). Measured in a real browser from
 * the computed style of every `.card` - a class list in a template can lie, the computed style cannot.
 */
class CardLookCest
{
    public function everyCardHasTheSameCornersAndAShadow(AcceptanceTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $user = $em->getRepository(UserEntity::class)->findOneBy(['username' => 'TestUsername']);
        $role = $em->getRepository(RoleEntity::class)->findOneBy([], ['id' => 'ASC']);

        Assert::assertNotNull($user);
        Assert::assertNotNull($role);

        $I->resizeWindow(1500, 1200);

        $radii = [];
        $shadows = [];
        $cards = 0;
        $problems = [];

        $measure = function (string $page) use ($I, &$radii, &$shadows, &$cards, &$problems) : void {
            $I->amOnPage($page);
            $I->wait(1);

            /** @var list<array{radius: string, shadow: string, cls: string}> $found */
            $found = $I->executeJS(<<<'JS'
                // a card under the mouse is lifted on purpose (the home page): its hover state is not its look
                return Array.from(document.querySelectorAll('.card')).filter(function (card) {
                    return !card.matches(':hover');
                }).map(function (card) {
                    var s = getComputedStyle(card);
                    return {
                        radius: [s.borderTopLeftRadius, s.borderTopRightRadius, s.borderBottomRightRadius, s.borderBottomLeftRadius].join(' '),
                        shadow: s.boxShadow,
                        cls: card.className
                    };
                });
JS);

            foreach ($found as $card) {
                $cards++;
                $radii[$card['radius']][] = $page . ' ' . $card['cls'];
                $shadows[$card['shadow']][] = $page . ' ' . $card['cls'];

                if ($card['shadow'] === 'none') {
                    $problems[] = $page . ': a card without a shadow (' . $card['cls'] . ')';
                }
            }
        };

        foreach (['/', '/web.user.login/', '/web.user.registration/', '/web.user.forget-password.request/'] as $page) {
            $measure($page);
        }

        $I->amOnPage('/web.user.login/');
        $I->fillField('username', 'TestUsername');
        $I->fillField('password', 'secret');
        $I->click('#frm-loginForm [type=submit]');
        $I->wait(1);

        foreach ([
            '/admin.user.list/', '/admin.user.create/', '/admin.user.edit/default/' . $user->uuid, '/admin.role.list/',
            '/admin.role.create/', '/admin.role.edit/default/' . $role->id, '/web.user.view/default/' . $user->uuid,
            '/web.user.edit/', '/web.user.change-password/', '/web.user.change-email/',
        ] as $page) {
            $measure($page);
        }

        Assert::assertGreaterThan(8, $cards, 'The scan did not find the cards.');
        Assert::assertSame([], array_slice($problems, 0, 15), implode("\n", array_slice($problems, 0, 15)));
        Assert::assertCount(1, $radii, "The cards have different corners:\n" . $this->describe($radii));
        Assert::assertCount(1, $shadows, "The cards have different shadows:\n" . $this->describe($shadows));
    }

    /**
     * @param array<string, list<string>> $groups
     */
    private function describe(array $groups) : string
    {
        $lines = [];

        foreach ($groups as $style => $cards) {
            $lines[] = $style . ' x' . count($cards) . ' e.g. ' . $cards[0];
        }

        return implode("\n", $lines);
    }
}
