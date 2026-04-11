<?php declare(strict_types=1);

namespace App\UI\Admin\User\List;

use App\Core\AutoLoginAuthenticator;
use App\UI\Admin\AdminBasePresenter;
use Contributte\Datagrid\Datagrid;
use Contributte\MenuControl\UI\MenuComponent;
use Contributte\MenuControl\UI\MenuComponentFactory;
use Nette\Application\UI\Presenter;
use Nette\Http\IResponse;
use Nette\Security\AuthenticationException;

class ListPresenter extends AdminBasePresenter
{
    public function __construct(
        private readonly UserDataGrid         $dataGridFactory,
        private readonly MenuComponentFactory $menuFactory,
    )
    {
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
