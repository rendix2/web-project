<?php declare(strict_types=1);

namespace App\UI\Admin\User\List;

use App\UI\Admin\AdminBasePresenter;
use Contributte\Datagrid\Datagrid;

class ListPresenter extends AdminBasePresenter
{
    public function __construct(
        private readonly UserDataGrid         $userDataGrid,
    )
    {
        parent::__construct();
    }

    protected function createComponentGrid() : Datagrid
    {
        return $this->userDataGrid->create();
    }
}
