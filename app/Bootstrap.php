<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;


class Bootstrap
{
    public static function boot() : Configurator
    {
        $sep = DIRECTORY_SEPARATOR;
        $dir = __DIR__;

        $appDir = dirname($dir);
        $binDir = $appDir . $sep . 'bin';
        $configDir = $appDir . $sep . 'config';
        $logDir = $appDir . $sep . 'log';
        $migrationsDir = $appDir . $sep . 'app' . $sep . 'Migrations';
        $tempDir = $appDir . $sep . 'temp';
        $cacheDir = $appDir . $sep . 'temp' . $sep . 'cache';
        $rootDir = $appDir . $sep;

        $configurator = new Configurator;

        $configurator->addStaticParameters(
            [
                'binDir' => $binDir,
                'cacheDir' => $cacheDir,
                'configDir' => $configDir,
                'migrationsDir' => $migrationsDir,
                'rootDir' => $rootDir,
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
}
