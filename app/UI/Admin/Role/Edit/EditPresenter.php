<?php

namespace App\UI\Admin\Role\Edit;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\UI\Admin\AdminBasePresenter;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Contributte\MenuControl\UI\MenuComponent;
use Contributte\MenuControl\UI\MenuComponentFactory;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Http\IResponse;
use Nette\Localization\Translator;
use stdClass;

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
        private readonly MenuComponentFactory       $menuFactory,
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

        $this['editForm']->setDefaults(
            [
                'name' => $roleEntity->name,
            ]
        );
    }

    public function renderDefault(string $id) : void
    {
        $this->template->roleEntity = $this->roleEntity;
    }

    protected function createComponentMenu() : MenuComponent
    {
        return $this->menuFactory->create('admin');
    }

    public function createComponentEditForm() : BootstrapForm
    {
        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');
        $form->setAjax(true);

        $form->addText('name', 'admin-role-edit.form.name.label')
            ->setRequired('admin-role-edit.form.name.required')
            ->setMaxLength(512);

        $form->addSubmit('send', 'admin-role-edit.form.submit.label');

        $form->onValidate[] = [$this, 'editFormOnValidate'];
        $form->onSuccess[] = [$this, 'editFormSuccess'];

        return $form;
    }

    public function editFormOnValidate(Form $form) : void
    {
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

        $roleEntity->name = $values->name;

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
        } catch (DbalException $exception) {
            $flash = new stdClass();
            $flash->place = 'general';
            $flash->type = 'danger';
            $flash->message = $exception->getMessage();

            $this->flashMessage($flash);
            $this->redrawControl('flashes');
        } catch (\Throwable $exception) {
            $flash = new stdClass();
            $flash->place = 'general';
            $flash->type = 'danger';
            $flash->message = $exception->getMessage();

            $this->flashMessage($flash);
            $this->redrawControl('flashes');
        }
    }

}