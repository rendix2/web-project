<?php declare(strict_types=1);

namespace App\DI;


use App\ConsoleModule\Commands\CreateDatabaseCommand;
use App\UI\Web\User\Login\UserLoginAttemptCheckService;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;

class ServicesExtension extends CompilerExtension
{

    public function loadConfiguration()
    {
        $this->registerCommands();

        $this->registerServices();
    }


    private function registerCommands() : void
    {
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('CreateDatabaseCommand'), (new ServiceDefinition())->setType(CreateDatabaseCommand::class));
    }

    private function registerServices() : void
    {
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('LoginAttemptCheckService'), (new ServiceDefinition())->setType(UserLoginAttemptCheckService::class));
    }

}
