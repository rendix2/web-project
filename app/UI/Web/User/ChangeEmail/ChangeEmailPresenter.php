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
use Nette\Http\IResponse;
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
    ) {
        parent::__construct();
    }

    public function actionDefault() : void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->error('Pro změnu e-mailu musíte být přihlášeni.', IResponse::S403_Forbidden);
        }

    }

    public function createComponentChangeEmailForm() : BootstrapForm
    {
        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');

        $form->addComponent(
            $this->passwordFormControlFactory->create((string) $this->translator->translate('web-user-changePassword.form.currentPassword.label')), 'currentPassword',
        );

        $emailControl = $this->emailFormControlFactory->create((string) $this->translator->translate('admin-user-edit.form.email.label'));
        $emailControl->setExcludeUserId($this->getUser()->getId());

        $form->addComponent($emailControl, 'email');

        $form->addSubmit('send', 'web-user-changeEmail.form.submit.name');

        $form->onValidate[] = [$this, 'changeEmailFormOnValidate'];
        $form->onSuccess[] = [$this, 'changeEmailFormSuccess'];

        return $form;
    }

    public function changeEmailFormOnValidate(Form $form) : void
    {
        $values = $form->getUntrustedValues(ChangeEmailValues::class);

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

        if (!$this->passwords->verify($values->currentPassword, $userEntity->password)) {
            $form->addError($this->translator->translate('web-user-changePassword.form.currentPassword.notMatch'));
        }
    }

    public function changeEmailFormSuccess(Form $form) : void
    {
        $values = $form->getValues(ChangeEmailValues::class);

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

        $userEntity->email = $values->email;
        $userEntity->updatedAt = new DateTimeImmutable();

        $userEmailEntity = new UserEmailEntity();
        $userEmailEntity->email = $values->email;
        $userEmailEntity->user = $userEntity;

        $userEntity->addUserEmailEntity($userEmailEntity);

        try {
            $this->em->persist($userEntity);
            $this->em->flush();

            $this->flashMessage(
                $this->translator->translate('web-user-changeEmail.form.submit.success'),
                'success'
            );
            $this->redrawControl('flashes');
            $this->redirect('this');
        } catch (DbalException $exception) {
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }
    }

}
