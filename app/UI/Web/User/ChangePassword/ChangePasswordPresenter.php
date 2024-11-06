<?php declare(strict_types=1);

namespace App\UI\Web\User\ChangePassword;

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
        private readonly EntityManagerDecorator $em,
        private readonly Translator             $translator,
        private readonly Passwords              $passwords,
    )
    {
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

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->addPassword('currentPassword', 'web-user-changePassword.form.currentPassword.label')
            ->setRequired('admin-user-edit.form.password.required')
            ->addRule(Form::MinLength, $this->translator->translate('admin-user-edit.form.password.ruleMinLength', ['minChars' => 8]), 8)

            ->addCondition(Form::MinLength, 8)
                ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastNumber', '.*[0-9].*')
                //->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleNotStartNumber', '^[^0-9].*')
                //->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleNotFinishNumber', '.*[^0-9]$')
                ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastLowerChar', '.*[a-z].*')
                ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastUpperChar', '.*[A-Z].*')
                //->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleNotStartUpperChar', '^[^A-Z].*')
                //->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleNotFinishUpperChar', '.*[^A-Z]$')
            ->endCondition();

        $form->addPassword('password', 'web-user-changePassword.form.newPassword.label')
            ->setRequired('admin-user-edit.form.password.required')
            ->addRule(Form::MinLength, $this->translator->translate('admin-user-edit.form.password.ruleMinLength', ['minChars' => 8]), 8)

            ->addCondition(Form::MinLength, 8)
                ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastNumber', '.*[0-9].*')
                //->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleNotStartNumber', '^[^0-9].*')
                //->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleNotFinishNumber', '.*[^0-9]$')
                ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastLowerChar', '.*[a-z].*')
                ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastUpperChar', '.*[A-Z].*')
                //->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleNotStartUpperChar', '^[^A-Z].*')
                //->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleNotFinishUpperChar', '.*[^A-Z]$')
            ->endCondition();

        $form->addPassword('password2', 'admin-user-edit.form.password2.label')
            ->setOmitted()
            ->setRequired('admin-user-edit.form.password2.required')
            ->addRule(Form::MinLength, $this->translator->translate('admin-user-edit.form.password2.ruleMinLength', ['minChars' => 8]), 8)

            ->addConditionOn($form['password'], Form::Filled, true)
                ->addRule(Form::Equal, 'admin-user-edit.form.password2.ruleEqual', $form['password'])
            ->endCondition();

        $form->addSubmit('changePassword', 'web-user-changePassword.form.submit.name');

        $form->onValidate[] = [$this, 'changePasswordFormOnValidate'];
        $form->onSuccess[] = [$this, 'changePasswordFormSuccess'];

        return $form;
    }

    public function changePasswordFormOnValidate(Form $form) : void
    {
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $this->getUser()->getIdentity()->getId()
                ]
            );

        if (!$userEntity) {
            $this->error('user not found');
        }

        if ($this->passwords->verify($form->getHttpData()['currentPassword'], $userEntity->password)) {
            foreach ($userEntity->passwords as $userPassword) {
                if ($this->passwords->verify($form->getHttpData()['password'], $userPassword->password)) {
                    $form->addError(
                        $this->translator->translate('admin-user-edit.form.password.alreadyUsed')
                    );
                }
            }
        } else {
            $form->addError($this->translator->translate('web-user-changePassword.form.currentPassword.notMatch'));
        }
    }

    public function changePasswordFormSuccess(Form $form) : void
    {
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $this->getUser()->getIdentity()->getId()
                ]
            );

        if (!$userEntity) {
            $this->error('user not found');
        }

        $userEntity->password = $this->passwords->hash($form->getValues()->password);
        $userEntity->updatedAt = new DateTimeImmutable();

        $userPasswordEntity = new UserPasswordEntity();
        $userPasswordEntity->user = $userEntity;
        $userPasswordEntity->password = $this->passwords->hash($form->getValues()->password);

        $userEntity->addUserPasswordEntity($userPasswordEntity);

        try {
            $this->em->persist($userEntity);
            $this->em->flush();

            $this->flashMessage(
                $this->translator->translate('web-user-changePassword.form.submit.success'),
                'success'
            );
            $this->redrawControl('flashes');
        } catch (DbalException $exception) {
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }
    }

}
