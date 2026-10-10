<?php

namespace App\UI\Admin\Role\Edit;

use App\Database\EntityManagerDecorator;
use App\Forms\Mapper\RoleFormMapper;
use App\Model\Entity\RoleEntity;
use App\UI\Admin\AdminBasePresenter;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Nette\Application\UI\Form;
use Nette\Http\IResponse;
use Nette\Localization\Translator;
use stdClass;
use Throwable;
use App\Forms\IconBootstrapForm;
use App\Forms\Renderer\IconBootstrapRenderer;

/**
 * class EditPresenter
 *
 * @package App\UI\Admin\Role\Edit
 */
class EditPresenter extends AdminBasePresenter
{

    private RoleEntity $roleEntity;

    public function __construct(
        private readonly Translator                 $translator,
        private readonly EntityManagerDecorator     $em,
        private readonly RoleFormMapper             $roleFormMapper,
    )
    {
        parent::__construct();
    }


    public function actionDefault(string $id) : void
    {
        $roleEntity = $this->em
            ->getRepository(RoleEntity::class)
            ->findOneBy(
                [
                    'id' => $id,
                ]
            );

        if ($roleEntity === null) {
            $this->error('Role not found', IResponse::S404_NotFound);
        }

        $this->roleEntity = $roleEntity;

        $this->roleFormMapper->fill($this['editForm'], $roleEntity);
    }

    public function renderDefault(string $id) : void
    {
        $this->getTemplate()->roleEntity = $this->roleEntity;
    }


    public function createComponentEditForm() : IconBootstrapForm
    {
        $form = new IconBootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->setAjax(true);

        $form->addText('name', 'admin-role-edit.form.name.label')
            ->setRequired('admin-role-edit.form.name.required')
            ->setMaxLength(512)
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-user-tag');

        $form->addSubmit('send', 'admin-role-edit.form.submit.label')
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-save');

        $form->onSuccess[] = [$this, 'editFormSuccess'];

        return $form;
    }

    public function editFormSuccess(Form $form) : void
    {
        $values = $form->getValues(EditRoleValues::class);

        $roleEntity = $this->em
            ->getRepository(RoleEntity::class)
            ->findOneBy(
                [
                    'id' => $this->getParameter('id'),
                ]
            );

        if ($roleEntity === null) {
            $this->error('Role not found');
        }

        $this->roleFormMapper->apply($values, $roleEntity);

        try {
            $this->em->persist($roleEntity);
            $this->em->flush();

            $flash = new stdClass();
            $flash->place = 'general';
            $flash->type = 'success';
            $flash->message = $this->translator->translate('admin-role-edit.form.submit.success', ['name' => $values->name]);

            $this->flashMessage($flash);
            $this->redrawControl('flashes');
            $this->redrawControl('editFormWrapper');
            $this->redrawControl('editForm');
        } catch (Throwable $exception) {
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