<?php declare(strict_types=1);

namespace App\UI\Admin\Role\List;

use App\UI\Admin\AdminBasePresenter;
use App\UI\Admin\User\List\UserDataGrid;
use Contributte\Datagrid\Datagrid;
use Contributte\MenuControl\UI\MenuComponent;
use Contributte\MenuControl\UI\MenuComponentFactory;

/**
 * class ListPresenter
 *
 * @package App\UI\Admin\Role\List
 */
class ListPresenter extends AdminBasePresenter
{

    public function __construct(
        private readonly RoleDataGrid         $roleDataGrid,
        private readonly MenuComponentFactory $menuFactory,
    )
    {
        parent::__construct();
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
        return $this->roleDataGrid->create();
    }

}