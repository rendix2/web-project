<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;


class Bootstrap
{
    public static function boot(): Configurator
    {
        $appDir = dirname(__DIR__);
        $logDir = $appDir . DIRECTORY_SEPARATOR . 'log';
        $tempDir = $appDir . DIRECTORY_SEPARATOR . 'temp';
        $configDir = $appDir . DIRECTORY_SEPARATOR . 'config';

        $configurator = new Configurator;

        //$configurator->setDebugMode('secret@23.75.345.200'); // enable for your remote IP
        $configurator->enableTracy($logDir);

        $configurator->setTempDirectory($tempDir);

        $configurator->createRobotLoader()
            ->addDirectory(__DIR__)
            ->register();

        $configurator->addConfig($configDir . DIRECTORY_SEPARATOR .'common.neon');
        $configurator->addConfig($configDir . DIRECTORY_SEPARATOR .'services.neon');
        $configurator->addConfig($configDir . DIRECTORY_SEPARATOR .'local.neon');

        return $configurator;
    }
}
