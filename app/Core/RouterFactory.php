<?php declare(strict_types=1);

namespace App\Core;

use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Nette\StaticClass;

final class RouterFactory
{

    use StaticClass;

    public static function createRouter() : RouteList
    {
        $router = new RouteList;

        $router->addRoute('<presenter>/<action>/<uuid>', [
                'presenter' => 'Web:Dashboard',
                'action' => 'edit',
                'uuid' => [
                    Route::Pattern => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
                ],
            ]
        );

        $router->addRoute('<presenter>/<action>/<id>', [
                'presenter' => 'Web:Dashboard',
                'action' => 'edit',
                'id' => [
                    Route::Pattern => '\d+',
                ],
            ]
        );

        $router->addRoute('<presenter>/<action>', 'Web:Dashboard:default');

        return $router;
    }

}
