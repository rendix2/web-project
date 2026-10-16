<?php declare(strict_types=1);

namespace App\UI\Admin\Role\Create;

use stdClass;
use App\Database\EntityManagerDecorator;
use App\Forms\Mapper\RoleFormMapper;
use App\UI\Admin\AdminBasePresenter;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use App\Forms\IconBootstrapForm;
use App\Forms\Renderer\IconBootstrapRenderer;

/**
 * class CreatePresenter
 *
 * @package App\UI\Admin\Role\Create
 */
class CreatePresenter extends AdminBasePresenter
{
    public function __construct(
        private readonly Translator                 $translator,
        private readonly EntityManagerDecorator     $em,
        private readonly RoleFormMapper             $roleFormMapper,
    )
    {
        parent::__construct();
    }


    public function createComponentCreateForm() : IconBootstrapForm
    {
        $form = new IconBootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->setAjax(true);

        $form->addText('name', 'admin-role-edit.form.name.label')
            ->setRequired('admin-role-edit.form.name.required')
            ->setMaxLength(512)
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-user-tag');

        $form->addSubmit('send', 'admin-role-create.form.submit.label')
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-plus');

        $form->onSuccess[] = [$this, 'createFormSuccess'];

        return $form;
    }

    public function createFormSuccess(Form $form) : void
    {
        $values = $form->getValues(CreateRoleValues::class);

        $roleEntity = $this->roleFormMapper->apply($values);

        try {
            $this->em->persist($roleEntity);
            $this->em->flush();

            $flash = new stdClass();
            $flash->place = 'general';
            $flash->type = 'success';
            $flash->message = $this->translator->translate('admin-role-create.form.submit.success', ['name' => $values->name]);

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