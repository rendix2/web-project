<?php declare(strict_types = 1);

namespace App;

use Contributte\Console\Application as ConsoleApplication;
use Nette\Application\Application as WebApplication;
use Nette\Bootstrap\Configurator;

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

		$migrationsDir = $appDir . $sep . 'Migrations';
		$translatonsDir = $appDir . $sep . 'Translations';

		$tempDir = $rootDir . 'temp' . $sep . $context;
		$cacheDir = $tempDir . $sep . 'cache';
		$proxiesDir = $tempDir . $sep . 'proxies';

		$configurator = new Configurator();

		$configurator->addStaticParameters(
			[
				'binDir' => $binDir,
				'cacheDir' => $cacheDir,
				'configDir' => $configDir,
				'migrationsDir' => $migrationsDir,
				'rootDir' => $rootDir,
				'translationsDir' => $translatonsDir,
				'proxiesDir' => $proxiesDir,
			]
		);

		//$configurator->setDebugMode('secret@23.75.345.200'); // enable for your remote IP
		$configurator->enableTracy($logDir);

		$configurator->setTempDirectory($tempDir);

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
