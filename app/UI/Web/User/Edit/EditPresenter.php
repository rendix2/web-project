<?php declare(strict_types=1);

namespace App\UI\Web\User\Edit;

use stdClass;
use App\Model\Entity\UserEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Http\IResponse;
use Nette\Localization\Translator;
use App\Database\EntityManagerDecorator;
use App\Forms\IconBootstrapForm;
use App\Forms\Renderer\IconBootstrapRenderer;

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
        parent::__construct();
    }

    public function actionDefault() : void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->error('user not logged in', IResponse::S403_Forbidden);
        }

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

        $this['editForm']->setDefaults(
            [
                'name' => $userEntity->name,
                'surname' => $userEntity->surname,
            ]
        );
    }

    protected function createComponentEditForm() : IconBootstrapForm
    {
        $form = new IconBootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);

        $form->addText('name', 'admin-user-edit.form.name.label')
            ->setRequired('admin-user-edit.form.name.required')
            ->setMaxLength(512)
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-id-card');

        $form->addText('surname', 'admin-user-edit.form.surname.label')
            ->setRequired('admin-user-edit.form.surname.required')
            ->setMaxLength(512)
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-id-card');

        $form->addSubmit('send', 'web-user-edit.form.submit.label')
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-save');

        $form->onSuccess[] = [$this, 'editFormSuccess'];

        return $form;
    }

    public function editFormSuccess(Form $form) : void
    {
        $values = $form->getValues(EditUserProfileValues::class);

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

        $userEntity->name = $values->name;
        $userEntity->surname = $values->surname;
        $userEntity->updatedAt = new DateTimeImmutable();

        try {
            $this->em->persist($userEntity);
            $this->em->flush();

            $flash = new stdClass();
            $flash->place = 'general';
            $flash->type = 'success';
            $flash->message = $this->translator->translate('web-user-edit.form.submit.success');

            $this->flashMessage($flash);
            $this->redrawControl('flashes');
        } catch (DbalException $exception) {
            bdump($exception);
            $flash = new stdClass();
            $flash->place = 'general';
            $flash->type = 'danger';
            $flash->message = $this->translator->translate('messages.operationFailed');

            $this->flashMessage($flash);
            $this->redrawControl('flashes');
        }
    }

}
