<?php declare(strict_types=1);

namespace App\UI\Admin\User\List;

use App\Model\Entity\UserEntity;
use Contributte\Datagrid\Column\Action\Confirmation\CallbackConfirmation;
use Contributte\Datagrid\Datagrid;
use Contributte\MenuControl\UI\MenuComponent;
use Contributte\MenuControl\UI\MenuComponentFactory;
use Contributte\Translation\Translator;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\QueryBuilder;
use Nette\Application\UI\Presenter;
use Nette\Forms\Container;
use Nette\Http\IResponse;
use Nette\Security\User;
use Nette\Utils\ArrayHash;
use Nettrine\ORM\EntityManagerDecorator;

class ListPresenter extends Presenter
{
    public function __construct(
        private readonly DataGridFactory      $dataGridFactory,
        private readonly MenuComponentFactory $menuFactory,
    )
    {
    }

    public function startup() : void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->error('Not logged in', IResponse::S401_Unauthorized);
        }

        if (!$this->getUser()->isInRole('Admin')) {
            $this->error('Forbidden', IResponse::S403_Forbidden);
        }
    }

    public function actionDefault() : void
    {
    }

    protected function createComponentMenu() : MenuComponent
    {
        return $this->menuFactory->create('admin');
    }

    protected function createComponentGrid() : Datagrid
    {
        return $this->dataGridFactory->create();
    }

}
