<?php declare(strict_types=1);

namespace App\UI\Admin\Role\List;

use App\UI\Admin\AdminBasePresenter;
use App\UI\Admin\User\List\UserDataGrid;
use Contributte\Datagrid\Datagrid;

/**
 * class ListPresenter
 *
 * @package App\UI\Admin\Role\List
 */
class ListPresenter extends AdminBasePresenter
{

    public function __construct(
        private readonly RoleDataGrid         $roleDataGrid,
    )
    {
        parent::__construct();
    }

    public function actionDefault() : void
    {
    }


    protected function createComponentGrid() : Datagrid
    {
        return $this->roleDataGrid->create();
    }

}