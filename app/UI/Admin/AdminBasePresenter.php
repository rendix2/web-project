<?php

namespace App\UI\Admin;

use App\Core\AutoLoginService;
use Nette\Application\UI\Presenter;
use Nette\DI\Attributes\Inject;
use Nette\Http\IResponse;

/**
 * class AdminBasePresenter
 *
 * @package App\UI\Admin
 */
abstract class AdminBasePresenter extends Presenter
{
    #[Inject]
    public AutoLoginService $autoLoginService;

    public function startup() : void
    {
        parent::startup();

        // the admin has to be logged in: the "stay logged in" cookie is the last chance, otherwise 401
        if (!$this->getUser()->isLoggedIn() && !$this->autoLoginService->login($this)) {
            $this->error('Not logged in', IResponse::S401_Unauthorized);
        }

        if (!$this->getUser()->isInRole('Admin')) {
            $this->error('Forbidden', IResponse::S403_Forbidden);
        }
    }

}