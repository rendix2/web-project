<?php declare(strict_types=1);

namespace App\UI\Web\User\Logout;

use App\Core\AutoLoginService;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use stdClass;

class LogoutPresenter extends Presenter
{
    public function __construct(
        private readonly Translator       $translator,
        private readonly AutoLoginService $autoLoginService,
    )
    {
        parent::__construct();
    }

    public function actionDefault() : void
    {
        // Odhlášení už odhlášeného není chyba (typicky dvojklik na odkaz, otevřená druhá záložka,
        // vrácení se v historii prohlížeče zpět) - stačí ho poslat na login, ne mu ukázat error page.
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect(':Web:User:Login:default');
        }

        // token "zůstat přihlášen" pryč z databáze i cookie z prohlížeče
        $this->autoLoginService->forget($this);

        $this->getUser()->logout(true);
        $flash = new stdClass();
        $flash->place = 'general';
        $flash->type = 'success';
        $flash->message = $this->translator->translate('web-user-logout.success');

        $this->flashMessage($flash);
        $this->redirect(':Web:User:Login:default');
    }
}
