<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Core\RouterFactory;
use Nette\Application\Routers\RouteList;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Routing\Router;
use Tests\Support\IntegrationTester;

/**
 * The router of the container is the one RouterFactory (a normal service, `@routerFactory::create()`) builds. Regression: with
 * `routing: cache: true` the container did not compile any more ("Call to undefined method RoutingExtension::getService()" - the
 * cache evaluates the body of the router service at compile time, which works only for a static factory call).
 */
class RouterServiceCest
{
    public function theRouterOfTheContainerComesFromTheRouterFactory(IntegrationTester $I) : void
    {
        $router = $I->grabService(Router::class);

        $I->assertInstanceOf(RouteList::class, $router);
        $I->assertCount(count((new RouterFactory())->create()->getRouters()), $router->getRouters());
        $I->assertInstanceOf(RouterFactory::class, $I->grabService(RouterFactory::class));
    }

    public function theRouterOfTheContainerMatchesTheRoutesOfTheApplication(IntegrationTester $I) : void
    {
        $router = $I->grabService(Router::class);
        $request = new Request(new UrlScript('http://example.com/admin.user.edit/default/12', '/'));

        $I->assertSame(['presenter' => 'Admin:User:Edit', 'action' => 'default', 'id' => '12'], $router->match($request));
    }
}
