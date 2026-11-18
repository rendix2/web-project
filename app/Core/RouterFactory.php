<?php declare(strict_types=1);

namespace App\Core;

use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;

/**
 * A normal service (config/services.neon `routerFactory`), the router service is `@routerFactory::create()`.
 */
final class RouterFactory
{
    public function create() : RouteList
    {
        $router = new RouteList();

        $router->addRoute('<presenter>/<action>/<uuid>', [
                'presenter' => 'Web:Home',
                'action' => 'edit',
                'uuid' => [
                    Route::Pattern => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
                ],
            ]);

        $router->addRoute('<presenter>/<action>/<id>', [
                'presenter' => 'Web:Home',
                'action' => 'edit',
                'id' => [
                    Route::Pattern => '\d+',
                ],
            ]);

        $router->addRoute('<presenter>/<action>', 'Web:Home:default');

        return $router;
    }
}
