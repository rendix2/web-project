<?php declare(strict_types=1);

namespace App;

use App\Core\AutoLoginService;
use Contributte\Console\Application as ConsoleApplication;
use Nette\Application\Application as WebApplication;
use Nette\Bootstrap\Configurator;
use Nette\Utils\FileSystem;

final class Bootstrap
{
    /**
     * True only inside the ghcr.io production image (.docker/web/prod.Dockerfile writes this
     * marker file at build time). Deliberately not based on IP/hostname: REMOTE_ADDR as seen
     * through Docker's port mapping is not reliably 127.0.0.1, so the previous IP+cookie based
     * detection left debug mode (and Tracy) permanently forced on for every visitor everywhere,
     * including production - see the removed unconditional setDebugMode(true) below.
     */
    public static function isProduction() : bool
    {
        return file_exists('/.docker-production-build');
    }

	public static function boot(string $context): Configurator
	{
		$sep = DIRECTORY_SEPARATOR;
		$dir = __DIR__;

		$projectDir = dirname($dir);
		$rootDir = $projectDir . $sep;

		$appDir = $rootDir . 'app';
		$binDir = $rootDir . 'bin';
		$configDir = $rootDir . 'config';
		$logDir = $rootDir . 'log';
		$logContextDir = $logDir . $sep . $context;
        $tempDir = $rootDir . 'temp';
        $wwwDir = $rootDir . 'www';

        $assetsDir = $wwwDir . $sep . 'assets';

		$databaseDir = $appDir . $sep . 'Database';
        $migrationsDir = $databaseDir . $sep . 'Migrations';
        $fixturesDir = $databaseDir . $sep . 'Fixtures';

        $modelDir = $appDir . $sep . 'Model';
        $entityDir = $modelDir . $sep . 'Entity';

		$tempContextDir = $tempDir . $sep . $context;
		$cacheDir = $tempContextDir . $sep . 'cache';
		$nettrineCacheDir = $cacheDir . $sep . 'nettrine';
        $mailDir = $tempContextDir . $sep . 'mails';

		$configurator = new Configurator();

		$configurator->addStaticParameters(
			[
				'binDir' => $binDir,
				'cacheDir' => $cacheDir,
				'configDir' => $configDir,
				'nettrineCacheDir' => $nettrineCacheDir,
				'migrationsDir' => $migrationsDir,
				'fixturesDir' => $fixturesDir,
				'rootDir' => $rootDir,
                'mailDir' => $mailDir,
                'logDir' => $logContextDir,
                'assetsDir' => $assetsDir,
                'entityDir' => $entityDir,
			]
		);

        $configurator->setDebugMode(!self::isProduction());

		$configurator->enableTracy($logContextDir);
		$configurator->setTempDirectory($tempContextDir);

		$configurator->createRobotLoader()
			->addDirectory($dir)
			->register();

		$configurator->addConfig($configDir . $sep . 'common.neon');
		$configurator->addConfig($configDir . $sep . 'services.neon');

        if ($configurator->isDebugMode()) {
            $configurator->addConfig($configDir . $sep . 'env' . $sep . 'dev.neon');
        } elseif (str_starts_with((is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : ''), 'https://test.')) {
            $configurator->addConfig($configDir . $sep . 'env' . $sep . 'test.neon');
        } else {
            $configurator->addConfig($configDir . $sep . 'env' . $sep . 'prod.neon');
        }

		return $configurator;
	}

	public static function runWebApplication(): void
	{
		$container = self::boot('web')->createContainer();
		$application = $container->getByType(WebApplication::class);

		// "Zůstat přihlášen" (viz LoginPresenter/AutoLoginService) musí platit na CELÉ veřejné
		// straně webu, ne jen v Adminu (ten má vlastní kontrolu v AdminBasePresenter::startup()) -
		// onPresenter se spustí pro každý request, hned po vytvoření presenteru, před jeho run().
		$application->onPresenter[] = [$container->getByType(AutoLoginService::class), 'tryLogin'];

		$application->run();
	}

	public static function runConsoleApplication(): void
	{
		self::boot('console')
			->createContainer()
			->getByType(ConsoleApplication::class)
			->run();
	}

}
