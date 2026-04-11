<?php declare(strict_types=1);

namespace App\UI\Web\User\ChangePassword;

use App\Forms\PasswordFormControlFactory;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use Nette\Security\Passwords;

class ChangePasswordPresenter extends Presenter
{
    public function __construct(
        private readonly EntityManagerDecorator     $em,
        private readonly Translator                 $translator,
        private readonly Passwords                  $passwords,
        private readonly PasswordFormControlFactory $passwordFormControlFactory,
    )
    {
        parent::__construct();
    }

    public function renderDefault() : void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('not logged id', 'danger');
            $this->redrawControl('flashes');
            return;
        }

        $this->template->userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $this->getUser()->getId()
                ]
            );
    }

    public function createComponentChangePasswordForm() : BootstrapForm
    {
        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');

        $form->addComponent(
            $this->passwordFormControlFactory->create((string) $this->translator->translate('web-user-changePassword.form.currentPassword.label')),
            'currentPassword'
        );

        $passwordControl = $this->passwordFormControlFactory->create((string) $this->translator->translate('web-user-changePassword.form.newPassword.label'));
        $form->addComponent($passwordControl, 'password');

        $form->addPassword('password2', 'admin-user-edit.form.password2.label')
            ->setOmitted()
            ->setRequired('admin-user-edit.form.password2.required')
            ->addRule(Form::MinLength, (string) $this->translator->translate('admin-user-edit.form.password2.ruleMinLength', ['minChars' => 8]), 8)

            ->addConditionOn($passwordControl, Form::Filled, true)
                ->addRule(Form::Equal, 'admin-user-edit.form.password2.ruleEqual', $passwordControl)
            ->endCondition();

        $form->addSubmit('send', 'web-user-changePassword.form.submit.name');

        $form->onValidate[] = [$this, 'changePasswordFormOnValidate'];
        $form->onSuccess[] = [$this, 'changePasswordFormSuccess'];

        return $form;
    }

    public function changePasswordFormOnValidate(Form $form) : void
    {
        $values = $form->getUntrustedValues(ChangePasswordValues::class);

        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $this->getUser()->getId(),
                ]
            );

        if ($userEntity === null) {
            $this->error('user not found');
        }

        if ($this->passwords->verify($values->currentPassword, $userEntity->password)) {
            foreach ($userEntity->passwords as $userPassword) {
                if ($this->passwords->verify($values->password, $userPassword->password)) {
                    $form->addError(
                        $this->translator->translate('admin-user-edit.form.password.alreadyUsed')
                    );
                    $this->redrawControl('changePasswordFormWrapper');
                    $this->redrawControl('changePasswordForm');
                    $this->redrawControl('flashes');
                    break;
                }
            }
        } else {
            $form->addError($this->translator->translate('web-user-changePassword.form.currentPassword.notMatch'));
        }
    }

    public function changePasswordFormSuccess(Form $form) : void
    {
        $values = $form->getValues(ChangePasswordValues::class);

        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $this->getUser()->getId(),
                ]
            );

        if ($userEntity === null) {
            $this->error('user not found');
        }

        $autologinKeys = $this->em
            ->getRepository(UserAutoLoginEntity::class)
            ->findBy(
                [
                    'user' => $userEntity,
                ]
            );

        $password = $this->passwords->hash($values->password);

        $userEntity->password = $password;
        $userEntity->updatedAt = new DateTimeImmutable();

        $userPasswordEntity = new UserPasswordEntity();
        $userPasswordEntity->user = $userEntity;
        $userPasswordEntity->password = $password;

        $userEntity->addUserPasswordEntity($userPasswordEntity);

        try {
            foreach ($autologinKeys as $autologinKey) {
                $this->em->remove($autologinKey);
            }

            $this->em->persist($userEntity);
            $this->em->flush();

            $this->flashMessage(
                $this->translator->translate('web-user-changePassword.form.submit.success'),
                'success'
            );
            $this->redrawControl('flashes');
            $this->redrawControl('changePasswordFormWrapper');
            $this->redrawControl('changePasswordForm');
        } catch (DbalException $exception) {
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }
    }

}
