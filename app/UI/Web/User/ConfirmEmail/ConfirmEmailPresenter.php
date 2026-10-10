<?php declare(strict_types=1);

namespace App\UI\Web\User\ConfirmEmail;

use App\Service\EmailChangeException;
use App\Service\UserEmailChangeService;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;

/**
 * The link of the confirmation mail sent to the NEW address of an account (see UserEmailChangeService).
 */
class ConfirmEmailPresenter extends Presenter
{
    public function __construct(
        private readonly UserEmailChangeService $emailChangeService,
        private readonly Translator             $translator,
    )
    {
        parent::__construct();
    }

    public function actionDefault(string $userId, string $key) : void
    {
        try {
            $this->emailChangeService->confirm($userId, $key);

            $this->flashMessage($this->translator->translate('web-user-confirmEmail.success'), 'success');
        } catch (EmailChangeException $exception) {
            $this->flashMessage($this->translator->translate('web-user-confirmEmail.' . $exception->reason), 'danger');
        }

        $this->redirect(':Web:User:Login:default');
    }

}
