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

		if (!file_exists($logDir)) {
			FileSystem::createDir($logDir);
		}

		if (!file_exists($tempDir)) {
			FileSystem::createDir($tempDir);
		}

		if (!file_exists($tempContextDir)) {
			FileSystem::createDir($tempContextDir);
		}

		if (!file_exists($cacheDir)) {
			FileSystem::createDir($cacheDir);
		}

		if (!file_exists($proxiesDir)) {
			FileSystem::createDir($proxiesDir);
		}

		if (!file_exists($sessionsDir)) {
			FileSystem::createDir($sessionsDir);
		}

		chmod($logDir, 0777);
		chmod($tempDir, 0777);
		chmod($tempContextDir, 0777);
		chmod($cacheDir, 0777);
		chmod($proxiesDir, 0777);
		chmod($sessionsDir, 0777);

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
			]
		);

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = getenv('NETTE_DEBUG_SECRET'). '@' . $_SERVER['REMOTE_ADDR'];

            //dump($ipAddress);
            $configurator->setDebugMode($ipAddress);
        } else {
            $configurator->setDebugMode(false);
        }

        //$configurator->setDebugMode(true);

		$configurator->enableTracy($logDir);
		$configurator->setTempDirectory($tempContextDir);

		$configurator->createRobotLoader()
			->addDirectory($dir)
			->register();

		$configurator->addConfig($configDir . $sep . 'common.neon');
		$configurator->addConfig($configDir . $sep . 'services.neon');
		$configurator->addConfig($configDir . $sep . 'env' . $sep . 'dev.neon');

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
