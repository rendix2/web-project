<?php declare(strict_types=1);

namespace App\UI\Web\User\Logout;

use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;

class LogoutPresenter extends Presenter
{
    public function __construct(
        private readonly Translator $translator,
    )
    {
    }

    public function actionDefault() : void
    {
        $this->getUser()->logout(true);
        $this->flashMessage(
            $this->translator->translate('web-user-logout.success'),
            'success'
        );
        $this->redirect(':Web:User:Login:default');
    }

}
