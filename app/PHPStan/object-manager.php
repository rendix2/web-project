<?php declare(strict_types=1);

use App\Bootstrap;
use Doctrine\ORM\EntityManagerInterface;

require __DIR__ . '/../../vendor/autoload.php';

return (new Bootstrap())->boot('console')->createContainer()
    ->getByType(EntityManagerInterface::class);
