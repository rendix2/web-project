<?php declare(strict_types=1);

use App\Bootstrap;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Loaders\RobotLoader;

require __DIR__ . '/../../vendor/autoload.php';

// Own context (temp/phpstan): the RobotLoader index, the DI container and the Doctrine proxies of PHPStan are not the ones of
// bin/console / make db, which may be stale or rebuilt by them in the middle of an analysis. The context creates its directories
// itself (like latte-lint): neither make nor CI knows them.
foreach ([__DIR__ . '/../../temp/phpstan', __DIR__ . '/../../log/phpstan'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

$entityManager = (new Bootstrap())->boot('phpstan')->createContainer()
    ->getByType(EntityManagerInterface::class);

// PHPStan asks the autoloaders about names that are no class (a function name like `count`, for one). In debug mode the first such
// name of a process makes the RobotLoader rescan app/ (unless it has scanned already in this process, as it does with an empty
// cache - which is why `make phpstan` wiped temp/ before) - inside PHPStan's autoload trap, where the scan cannot load
// Nette\Utils\Finder: the parallel workers failed at random with "Class Nette\Utils\Finder not found" and then
// "Class ...Entity does not exist" (family-tree 2026-11-18, the stack trace: AutoloadSourceLocator -> RobotLoader::tryLoad('count')
// -> refreshClasses()). So the index is refreshed once here, outside the trap: it is up to date (new classes included) and the
// loader never rescans in this process again. RobotLoader::register() registers the closure $this->tryLoad(...), hence the
// reflection. (From family-tree a303fa65.)
foreach (spl_autoload_functions() as $autoloader) {
    if ($autoloader instanceof Closure) {
        $loader = (new ReflectionFunction($autoloader))->getClosureThis();

        if ($loader instanceof RobotLoader) {
            $loader->refresh();
        }
    }
}

return $entityManager;
