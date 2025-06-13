<?php declare(strict_types=1);

namespace App\ConsoleModule\Commands;

use Nette\DI\Container;
use PDO;
use PDOException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'database:create',
    description: 'Create database',
)]
class CreateDatabaseCommand extends Command
{

    public function __construct(
        private readonly Container $container,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $databaseConnections = $this->container->getParameters()['database'];

        foreach ($databaseConnections as $databaseConnection) {
            try {
                $pdo = new PDO('mysql:host=' . $databaseConnection['host'], $databaseConnection['username'], $databaseConnection['password']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $databaseConnection['database'] . '` CHARACTER SET ' . $databaseConnection['charset'] . ' COLLATE '.$databaseConnection['collation'].';');
                $output->writeln('Database "' . $databaseConnection['database'] . '" created (if it did not exist).');
            } catch (PDOException $e) {
                $output->writeln('Error: Could not connect to database: ' . $e->getMessage() . "\n");

                return 1;
            }
        }

        return 0;
    }
}
