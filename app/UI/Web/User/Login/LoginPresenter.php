<?php declare(strict_types=1);

namespace App\UI\Web\User\Login;

use Contributte\FormsBootstrap\BootstrapForm;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;

class LoginPresenter extends Presenter
{

    public function __construct(
        private readonly Translator $translator,
    )
    {
    }

    public function renderDefault() : void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->flashMessage('You are already logged in', 'danger');
        }
    }

    public function createComponentLoginForm() : BootstrapForm
    {
        $form = new BootstrapForm();

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');

        $form->addText('username', 'web-user-login.form.username.label')
            ->setRequired('web-user-login.form.username.required')
            ->setMaxLength(512);

        $form->addPassword('password', 'web-user-login.form.password.label')
            ->setRequired('web-user-login.form.password.required');

        $submitButton = $form->addSubmit('login', 'web-user-login.form.submit.name')
            ->setHtmlAttribute('class', 'mt-2');

        if ($this->getUser()->isLoggedIn()) {
            $submitButton
                ->setDisabled();
        }

        $form->onSuccess[] = [$this, 'loginFormSuccess'];

        return $form;
    }

    public function loginFormSuccess(Form $form) : void
    {
        try {
            $this->getUser()->login($form->getValues()['username'], $form->getValues()['password']);

            $this->flashMessage(
                $this->translator->translate('web-user-login.form.submit.success'),
                'success'
            );
            $this->redirect(':Web:Home:default');
        } catch (\Nette\Security\AuthenticationException $e) {
            $this->flashMessage($e->getMessage(), 'danger');
        }
    }

}
