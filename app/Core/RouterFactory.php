<?php declare(strict_types=1);

namespace App\Core;

use Nette\Application\Routers\RouteList;
use Nette\StaticClass;

final class RouterFactory
{

    use StaticClass;

    public static function createRouter() : RouteList
    {
        $router = new RouteList();
        $router->addRoute('<presenter>/<action>[/<id>]', 'Web:Home:default');

        return $router;
    }

}
