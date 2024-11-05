<?php

namespace App\UI\Web\User\Edit;

use App\Model\Entity\UserEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Doctrine\DBAL\Exception;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Http\IResponse;
use Nette\Http\Response;
use Nette\Localization\Translator;
use Nettrine\ORM\EntityManagerDecorator;

/**
 * class EditPresenter
 *
 * @package App\UI\Web\User\Edit
 */
class EditPresenter extends Presenter
{

    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly Translator             $translator,
    ) {
    }

    public function actionDefault() : void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->error('user not logged in', IResponse::S403_Forbidden);
        }
    }

    protected function createComponentEditForm() : BootstrapForm
    {
        $form = new BootstrapForm();

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->addText('name', 'admin-user-edit.form.name.label')
            ->setRequired('admin-user-edit.form.name.required')
            ->setMaxLength(512);

        $form->addText('surname', 'admin-user-edit.form.surname.label')
            ->setRequired('admin-user-edit.form.surname.required')
            ->setMaxLength(512);

        $form->addSubmit('change', 'web-user-edit.form.submit.label');

        $form->onSuccess[] = [$this, 'editFormSuccess'];

        return $form;
    }

    public function editFormSuccess(Form $form) : void
    {
        $values = $form->getValues();

        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $this->getUser()->getId(),
                ]
            );

        $userEntity->name = $values->name;
        $userEntity->surname = $values->surname;

        try {
            $this->em->persist($userEntity);
            $this->em->flush();

            $this->flashMessage(
                $this->translator->translate('web-user-edit.submit.success'),
                'success',
            );
            $this->redrawControl('flashes');
        } catch (Exception $exception) {
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }
    }

}
