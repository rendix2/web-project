<?php declare(strict_types=1);

namespace App\UI\Web\User\ChangeEmail;

use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use Nette\Security\Passwords;
use Nettrine\ORM\EntityManagerDecorator;

/**
 * class ChangeEmailPresenter
 *
 * @package App\UI\Web\User\ChangeEmail
 */
class ChangeEmailPresenter extends Presenter
{

    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly Translator             $translator,
        private readonly Passwords              $passwords,
    ) {
    }

    public function actionDefault() : void
    {
    }

    public function createComponentChangeEmailForm() : BootstrapForm
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

        $form->addEmail('email', 'admin-user-edit.form.email.label')
            ->setRequired('admin-user-edit.form.email.required')
            ->setMaxLength(512);

        $form->addSubmit('changePassword', 'web-user-changeEmail.form.submit.name');

        $form->onValidate[] = [$this, 'changeEmailFormOnValidate'];
        $form->onSuccess[] = [$this, 'changeEmailFormSuccess'];

        return $form;
    }

    protected function changeEmailFormOnValidate(Form $form) : void
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

        $emailExists = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'email' => $form->getHttpData()['email']
                ]
            );

        if ($emailExists) {
            $form->addError(
                $this->translator->translate('admin-user-edit.form.email.exists', ['email' => $form->getHttpData()['email']])
            );
        }

        $historyEmailExists = $this->em
            ->getRepository(UserEmailEntity::class)
            ->findOneBy(
                [
                    'email' => $form->getHttpData()['email']
                ]
            );

        if ($historyEmailExists) {
            $form->addError(
                $this->translator->translate('admin-user-edit.form.email.exists', ['email' => $form->getHttpData()['email']])
            );
        }
    }

    public function changeEmailFormSuccess(Form $form) : void
    {
        $values = $form->getValues();

        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $this->getUser()->getId(),
                ]
            );

        if (!$userEntity) {
            $this->error('user not found');
        }

        $userEntity->email = $values->email;
        $userEntity->updatedAt = new DateTimeImmutable();

        $userEmailEntity = new UserEmailEntity();
        $userEmailEntity->email = $values->email;
        $userEmailEntity->user = $userEntity;

        $userEntity->addUserEmailEntity($userEmailEntity);

        try {
            $this->em->persist($userEntity);
            $this->em->flush();
        } catch (DbalException $exception) {
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }
    }

}
