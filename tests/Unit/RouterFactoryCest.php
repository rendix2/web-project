<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Core\RouterFactory;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tests\Support\UnitTester;

/**
 * The routes of the application: RouterFactory is a normal service now (no static `createRouter()`), its `create()` builds the
 * same routes as before - an id, a uuid, a bare presenter/action, and the home page.
 */
class RouterFactoryCest
{
    private const string BASE = 'http://example.com/';

    public function theHomePageIsTheDefaultActionOfWebHome(UnitTester $I) : void
    {
        $I->assertSame(['presenter' => 'Web:Home', 'action' => 'default'], $this->match(''));
    }

    public function aPresenterAndAnActionAreMatched(UnitTester $I) : void
    {
        $I->assertSame(['presenter' => 'Web:User:Login', 'action' => 'default'], $this->match('web.user.login/default'));
    }

    public function aNumericIdIsMatched(UnitTester $I) : void
    {
        $I->assertSame(['presenter' => 'Admin:User:Edit', 'action' => 'default', 'id' => '12'], $this->match('admin.user.edit/default/12'));
    }

    public function aUuidIsMatched(UnitTester $I) : void
    {
        $uuid = '0a1b2c3d-4e5f-6a7b-8c9d-0e1f2a3b4c5d';

        $I->assertSame(['presenter' => 'Web:User:Activation', 'action' => 'default', 'uuid' => $uuid], $this->match('web.user.activation/default/' . $uuid));
    }

    public function anIdThatIsNoNumberAndNoUuidIsNotMatched(UnitTester $I) : void
    {
        $I->assertNull((new RouterFactory())->create()->match($this->request('admin.user.edit/default/abc')));
    }

    public function aLinkIsBuiltBackToTheSameUrl(UnitTester $I) : void
    {
        $router = (new RouterFactory())->create();
        $url = $router->constructUrl(['presenter' => 'Admin:User:Edit', 'action' => 'default', 'id' => 12], new UrlScript(self::BASE, '/'));

        $I->assertSame(self::BASE . 'admin.user.edit/default/12', $url);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function match(string $path) : ?array
    {
        return (new RouterFactory())->create()->match($this->request($path));
    }

    private function request(string $path) : Request
    {
        return new Request(new UrlScript(self::BASE . $path, '/'));
    }
}
