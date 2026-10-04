<?php declare(strict_types=1);

use Nette\Loaders\RobotLoader;
use Nette\Utils\FileSystem;

require __DIR__ . '/../vendor/autoload.php';

$rootDir = dirname(__DIR__);
$robotLoaderTempDir = $rootDir . '/temp/robotloader-tests';

FileSystem::createDir($robotLoaderTempDir);

$robotLoader = new RobotLoader();
$robotLoader->setTempDirectory($robotLoaderTempDir);
$robotLoader->addDirectory($rootDir . '/app');
$robotLoader->register();
