<?php

namespace App\UI\Admin;

use App\Core\AutoLoginAuthenticator;
use Nette\Application\UI\Presenter;
use Nette\DI\Attributes\Inject;
use Nette\Http\IResponse;
use Nette\Security\AuthenticationException;

/**
 * class AdminBasePresenter
 *
 * @package App\UI\Admin
 */
abstract class AdminBasePresenter extends Presenter
{
    #[Inject]
    public AutoLoginAuthenticator $autoLoginAuthenticator;

    public function startup() : void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->getUser()->setAuthenticator($this->autoLoginAuthenticator);

            $autoLoginCookie = $this->getHttpRequest()->getCookie(AutoLoginAuthenticator::COOKIE_NAME);

            if ($autoLoginCookie === null) {
                $this->error('Not logged in', IResponse::S401_Unauthorized);
            } else {
                try {
                    $this->getUser()->login($autoLoginCookie, '');
                } catch (AuthenticationException $exception) {
                    $this->error('Not logged in', IResponse::S401_Unauthorized);
                }
            }
        }

        if (!$this->getUser()->isInRole('Admin')) {
            $this->error('Forbidden', IResponse::S403_Forbidden);
        }
    }

}