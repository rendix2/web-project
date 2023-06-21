<?php

namespace App\Presenters;

use Contributte\FormsBootstrap\BootstrapForm;
use JetBrains\PhpStorm\Deprecated;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;

#[Deprecated]
class ExamplePresenter extends Presenter
{
    public function createComponentTestForm() : Form
    {
        $form = new BootstrapForm();

        $form->addProtection('Please try again.');

        $form->addText('name', 'Name')
            ->setRequired('Name should be filled.');

        $form->addText('surname', 'Surname')
            ->setRequired('Surname should be filled.');

        $form->addSubmit('submit', 'Save');

        $form->onSuccess[] = [$this, 'testFormSuccess'];

        return $form;
    }

    public function testFormSuccess(Form $form) : void
    {
        $values = $form->values;
        $fullName = sprintf('%s %s', $values->name, $values->surname);

        $this->flashMessage($fullName, 'success');
    }

}