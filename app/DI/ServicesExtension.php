<?php declare(strict_types=1);

namespace App\DI;


use App\ConsoleModule\Commands\CreateDatabaseCommand;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;

class ServicesExtension extends CompilerExtension
{

    public function loadConfiguration()
    {
        $this->registerCommands();
    }


    private function registerCommands() : void
    {
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('CreateDatabaseCommand'), (new ServiceDefinition())->setType(CreateDatabaseCommand::class));
    }

}
