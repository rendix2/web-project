<?php declare(strict_types=1);

namespace App\UI\Web\User\ChangeEmail;

use App\Forms\EmailFormControlFactory;
use App\Forms\PasswordFormControlFactory;
use App\Forms\UsernameFormControlFactory;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Repository\UserRepository;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use Nette\Security\Passwords;
use App\Database\EntityManagerDecorator;

/**
 * class ChangeEmailPresenter
 *
 * @package App\UI\Web\User\ChangeEmail
 */
class ChangeEmailPresenter extends Presenter
{

    public function __construct(
        private readonly EntityManagerDecorator     $em,
        private readonly Translator                 $translator,
        private readonly Passwords                  $passwords,
        private readonly PasswordFormControlFactory $passwordFormControlFactory,
        private readonly EmailFormControlFactory    $emailFormControlFactory,
        private readonly UsernameFormControlFactory $usernameFormControlFactory,
    ) {
    }

    public function actionDefault() : void
    {
    }

    public function createComponentChangeEmailForm() : BootstrapForm
    {
        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');

        $form->addComponent(
            $this->passwordFormControlFactory->create($this->translator->translate('web-user-changePassword.form.currentPassword.label')), 'currentPassword',
        );

        $emailControl = $this->emailFormControlFactory->create($this->translator->translate('admin-user-edit.form.email.label'));
        $emailControl->setExcludeUserId($this->getUser()->getId());

        $form->addComponent($emailControl, 'email');

        $form->addSubmit('changePassword', 'web-user-changeEmail.form.submit.name');

        $form->onValidate[] = [$this, 'changeEmailFormOnValidate'];
        $form->onSuccess[] = [$this, 'changeEmailFormSuccess'];

        return $form;
    }

    public function changeEmailFormOnValidate(Form $form) : void
    {
        /**
         * @var UserRepository $userRepository
         */
        $userRepository = $this->em
            ->getRepository(UserEntity::class);

        /**
         * @var ?UserEntity $userEntity
         */
        $userEntity = $userRepository
            ->findOneById((string) $this->getUser()->getId());

        if ($userEntity === null) {
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
