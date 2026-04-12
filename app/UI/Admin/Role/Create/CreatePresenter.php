<?php declare(strict_types=1);

namespace App\UI\Admin\Role\Create;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\UI\Admin\AdminBasePresenter;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Contributte\MenuControl\UI\MenuComponent;
use Contributte\MenuControl\UI\MenuComponentFactory;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;

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
        private readonly MenuComponentFactory       $menuFactory,
    )
    {
        parent::__construct();
    }

    protected function createComponentMenu() : MenuComponent
    {
        return $this->menuFactory->create('admin');
    }

    public function createComponentCreateForm() : BootstrapForm
    {
        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');
        $form->setAjax(true);

        $form->addText('name', 'admin-role-edit.form.name.label')
            ->setRequired('admin-role-edit.form.name.required')
            ->setMaxLength(512);

        $form->addSubmit('send', 'admin-role-create.form.submit.label');

        $form->onSuccess[] = [$this, 'createFormSuccess'];

        return $form;
    }

    public function createFormSuccess(Form $form) : void
    {
        $values = $form->getValues(CreateRoleValues::class);

        $roleEntity = new RoleEntity();
        $roleEntity->name = $values->name;

        try {
            $this->em->persist($roleEntity);
            $this->em->flush();

            $this->flashMessage(
                $this->translator->translate('admin-role-create.form.submit.success', ['name' => $values->name]),
                'success'
            );
            $this->redrawControl('flashes');
            //$this->redirect('this');
        } catch (DbalException $exception) {
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }
    }
}