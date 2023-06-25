<?php declare(strict_types = 1);

namespace App\Presenters;

use Contributte\FormsBootstrap\BootstrapForm;
use GuzzleHttp\Client;
use JetBrains\PhpStorm\Deprecated;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;

#[Deprecated]
class ExamplePresenter extends Presenter
{

	public function __construct(
		private Client $client,
		private Translator $translator,
	)
	{
	}

	public function renderBootstrap(): void
	{
	}

	public function renderGuzzle(): void
	{
		$this->template->json = $this->client->get('http://httpbin.org/get')->getBody()->getContents();
	}

	public function createComponentTestForm(): Form
	{
		$form = new BootstrapForm();

		$form->setTranslator($this->translator);
		$form->addProtection('Please try again.');

		$form->addText('name', 'example.form.name.label')
			->setRequired('example.form.name.required');

		$form->addText('surname', 'example.form.surname.label')
			->setRequired('example.form.surname.required');

		$form->addSubmit('submit', 'example.form.save');

		$form->onSuccess[] = [$this, 'testFormSuccess'];

		return $form;
	}

	public function testFormSuccess(Form $form): void
	{
		$values = $form->values;
		$fullName = sprintf('%s %s', $values->name, $values->surname);

		$this->flashMessage($fullName, 'success');
	}

}
