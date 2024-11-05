<?php declare(strict_types=1);

namespace App;

use Contributte\Console\Application as ConsoleApplication;
use Nette\Application\Application as WebApplication;
use Nette\Bootstrap\Configurator;
use Nette\Utils\FileSystem;

final class Bootstrap
{

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
		$databaseDir = $appDir . $sep . 'Database';

		$migrationsDir = $databaseDir . $sep . 'Migrations';
		$fixturesDir = $databaseDir . $sep . 'Fixtures';

		$tempDir = $rootDir . 'temp';
		$tempContextDir = $tempDir . $sep . $context;

		$cacheDir = $tempContextDir . $sep . 'cache';
		$proxiesDir = $tempContextDir . $sep . 'proxies';
		$sessionsDir = $tempContextDir . $sep . 'sessions';
        $mailDir = $tempContextDir . $sep . 'mails';

		$configurator = new Configurator();

		$configurator->addStaticParameters(
			[
				'binDir' => $binDir,
				'cacheDir' => $cacheDir,
				'configDir' => $configDir,
				'migrationsDir' => $migrationsDir,
				'fixturesDir' => $fixturesDir,
				'rootDir' => $rootDir,
				'proxiesDir' => $proxiesDir,
                'sessionsDir' => $sessionsDir,
                'mailDir' => $mailDir,
			]
		);

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = getenv('NETTE_DEBUG_SECRET'). '@' . $_SERVER['REMOTE_ADDR'];

            //dump($ipAddress);
            $configurator->setDebugMode($ipAddress);
        } else {
            $configurator->setDebugMode(false);
        }

        $configurator->setDebugMode(true);

		$configurator->enableTracy($logDir);
		$configurator->setTempDirectory($tempContextDir);

		$configurator->createRobotLoader()
			->addDirectory($dir)
			->register();

		$configurator->addConfig($configDir . $sep . 'common.neon');
		$configurator->addConfig($configDir . $sep . 'services.neon');

        if ($configurator->isDebugMode()) {
            $configurator->addConfig($configDir . $sep . 'env' . $sep . 'dev.neon');
        } elseif (str_starts_with($_SERVER['REQUEST_URI'], 'https://test.')) {
            $configurator->addConfig($configDir . $sep . 'env' . $sep . 'test.neon');
        } else {
            $configurator->addConfig($configDir . $sep . 'env' . $sep . 'prod.neon');
        }

		return $configurator;
	}

	public static function runWebApplication(): void
	{
		self::boot('web')
			->createContainer()
			->getByType(WebApplication::class)
			->run();
	}

	public static function runConsoleApplication(): void
	{
		self::boot('console')
			->createContainer()
			->getByType(ConsoleApplication::class)
			->run();
	}

}
