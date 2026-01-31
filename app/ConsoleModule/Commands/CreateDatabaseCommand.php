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
        $dbConfig = $this->container->getParameters()['database']['default'];

        $host = $dbConfig['host'];
        $user = $dbConfig['username'];
        $password = $dbConfig['password'];
        $database = $dbConfig['database'];
        $dsn = $dbConfig['dsn'];
        $charset = $dbConfig['charset'];

        try {
            $pdo = new PDO($dsn, $user, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $pdo = new PDO('pgsql:host=' . $host . ';dbname=postgres', $user, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $stmt = $pdo->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
                $stmt->execute([$database]);
                $exists = $stmt->fetchColumn();

                if (!$exists) {
                    $pdo->exec('CREATE DATABASE "' . $database . '" WITH ENCODING ' . ($charset === 'utf8mb4' ? "'UTF8'" : "'" . $charset . "'") . ';');
                    $output->writeln('Database "' . $database . '" created.');
                } else {
                    $output->writeln('Database "' . $database . '" already exists.');
                }

            $output->writeln('Database "' . $database . '" created (if it did not exist).');

            return 0;
        } catch (PDOException $e) {
            $output->writeln('Error: Could not connect to database: ' . $e->getMessage() . "\n");

            return 1;
        }
    }
}