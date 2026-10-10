<?php declare(strict_types=1);

namespace App\UI\Admin\User\List;

use App\Core\AutoLoginAuthenticator;
use App\UI\Admin\AdminBasePresenter;
use Contributte\Datagrid\Datagrid;
use Nette\Application\UI\Presenter;
use Nette\Http\IResponse;
use Nette\Security\AuthenticationException;

class ListPresenter extends AdminBasePresenter
{
    public function __construct(
        private readonly UserDataGrid         $userDataGrid,
    )
    {
        parent::__construct();
    }

    public function actionDefault() : void
    {
    }


    protected function createComponentGrid() : Datagrid
    {
        return $this->userDataGrid->create();
    }

}
