<?php

namespace App\UI\Web\User\ForgetPassword\Change;

use App\Forms\PasswordFormControlFactory;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use App\Model\Entity\UserPasswordRequestEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Http\IResponse;
use Nette\Localization\Translator;
use App\Database\EntityManagerDecorator;
use Nette\Security\Passwords;

class ChangePresenter extends Presenter
{

    public function __construct(
        private readonly EntityManagerDecorator     $em,
        private readonly Translator                 $translator,
        private readonly PasswordFormControlFactory $passwordFactory,
        private readonly Passwords                  $passwords,
    )
    {
        parent::__construct();
    }

    public function actionDefault(string $userId, string $forgetKey): void
    {
        /**
         * @var ?UserEntity $userEntity
         */
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $this->getParameter('userId'),
                ]
            );

        /**
         * @var ?UserPasswordRequestEntity $request
         */
        $request = $this->em
            ->getRepository(UserPasswordRequestEntity::class)
            ->findOneBy(
                [
                    'forgetKey' => $forgetKey,
                    'user' => $userId,
                ]
            );

        if ($request === null) {
            $this->error('Tento odkaz pro obnovu hesla je neplatný.', IResponse::S404_NotFound);
        }

        if ((new DateTimeImmutable()) > $request->validUntil) {
            $oldRequests = $this->em
                ->getRepository(UserPasswordRequestEntity::class)
                ->findBy(
                    [
                        'user' => $userEntity
                    ]
                );

            foreach ($oldRequests as $oldRequest) {
                $this->em->remove($oldRequest);
            }

            $this->em->flush();
            $this->error('Tento odkaz pro obnovu hesla je expirovaný.', IResponse::S403_Forbidden);
        }
    }

    public function renderDefault(string $userId, string $forgetKey) : void
    {
    }

    public function createComponentChangePasswordForm() : BootstrapForm
    {
        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');

        $passwordControl = $this->passwordFactory->create((string) $this->translator->translate('web-user-changePassword.form.newPassword.label'));
        $form->addComponent($passwordControl, 'password');

        $form->addPassword('password2', 'admin-user-edit.form.password2.label')
            ->setOmitted()
            ->setRequired('admin-user-edit.form.password2.required')
            ->addRule(Form::MinLength, $this->translator->translate('admin-user-edit.form.password2.ruleMinLength', ['minChars' => 8]), 8)

            ->addConditionOn($passwordControl, Form::Filled, true)
            ->addRule(Form::Equal, 'admin-user-edit.form.password2.ruleEqual', $passwordControl)
            ->endCondition();

        $form->addSubmit('send', 'web-user-forgetPassword-request.form.submit.label');

        $form->onValidate[] = [$this, 'changePasswordFormOnValidate'];
        $form->onSuccess[] = [$this, 'changePasswordFormSuccess'];

        return $form;
    }

    public function changePasswordFormOnValidate(Form $form) : void
    {
        /**
         * @var ?UserEntity $userEntity
         */
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $this->getParameter('userId')
                ]
            );

        if ($userEntity === null) {
            $this->error('user not found');
        }

        $values = $form->getUntrustedValues(ChangePasswordValues::class);

        foreach ($userEntity->passwords as $userPassword) {
            if ($this->passwords->verify($values->password, $userPassword->password)) {
                $form->addError(
                    $this->translator->translate('admin-user-edit.form.password.alreadyUsed')
                );
                $this->redrawControl('editFormWrapper');
                $this->redrawControl('editForm');
                $this->redrawControl('flashes');
                break;
            }
        }
    }

    public function changePasswordFormSuccess(Form $form) : void
    {
        $values = $form->getValues(ChangePasswordValues::class);

        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $this->getParameter('userId'),
                ]
            );

        if ($userEntity === null) {
            $this->error('user not found');
        }

        $request = $this->em
            ->getRepository(UserPasswordRequestEntity::class)
            ->findOneBy(
                [
                    'user' => $userEntity
                ]
            );

        if ($request === null) {
            $this->error('request not found');
        }

        $oldRequests = $this->em
            ->getRepository(UserPasswordRequestEntity::class)
            ->findBy(
                [
                    'user' => $userEntity
                ]
            );

        $autologinKeys = $this->em
            ->getRepository(UserAutoLoginEntity::class)
            ->findBy(
                [
                    'user' => $userEntity,
                ]
            );

        $hashedPassword = $this->passwords->hash($values->password);

        $userEntity->password = $hashedPassword;
        $userEntity->updatedAt = new DateTimeImmutable();

        $userPasswordEntity = new UserPasswordEntity();
        $userPasswordEntity->user = $userEntity;
        $userPasswordEntity->password = $hashedPassword;

        $userEntity->addUserPasswordEntity($userPasswordEntity);

        try {
            foreach ($autologinKeys as $autologinKey) {
                $this->em->remove($autologinKey);
            }

            foreach ($oldRequests as $oldRequest) {
                $this->em->remove($oldRequest);
            }

            $this->em->persist($userEntity);
            $this->em->flush();

            $this->flashMessage(
                $this->translator->translate('web-user-changePassword.form.submit.success'),
                'success'
            );
            $this->redrawControl('flashes');
            $this->redrawControl('editFormWrapper');
            $this->redrawControl('editForm');

            $this->redirect(':Web:User:Login:default');
        } catch (DbalException $exception) {
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }
    }

}
