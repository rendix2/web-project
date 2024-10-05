<?php declare(strict_types=1);

namespace App\Presenters;

use App\Model\Entity\UserEntity;
use Chatbot\App\Model\Entity\CategoryEntity;
use Contributte\Datagrid\Column\Action\Confirmation\CallbackConfirmation;
use Contributte\Datagrid\Datagrid;
use Contributte\FormsBootstrap\BootstrapForm;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use GuzzleHttp\Client;
use JetBrains\PhpStorm\Deprecated;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Forms\Container;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;
use Nettrine\ORM\EntityManagerDecorator;

#[Deprecated]
class ExamplePresenter extends Presenter
{
    public function __construct(
        private Client                 $client,
        private Translator             $translator,
        private EntityManagerDecorator $em,
    )
    {
    }

    public function renderBootstrap() : void
    {
    }

    public function renderGuzzle() : void
    {
        $this->template->json = $this->client->get('http://httpbin.org/get')->getBody()->getContents();
    }

    public function createComponentTestForm() : Form
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

    public function testFormSuccess(Form $form) : void
    {
        $values = $form->values;
        $fullName = sprintf('%s %s', $values->name, $values->surname);

        $this->flashMessage($fullName, 'success');
    }

    public function createComponentGrid(string $name) : Datagrid
    {
        $dataSource = $this->em
            ->getRepository(UserEntity::class)
            ->createQueryBuilder('_user');

        $grid = new Datagrid();

        $grid->setDataSource($dataSource);
        $grid->setDefaultPerPage(10);
        $grid->setColumnsHideable();

        $grid->addColumnNumber('id', 'ID')
            ->setDefaultHide(true)
            ->setSortable(true)
            ->setFilterText()
            ->setPlaceholder('Search by ID');

        $grid->addColumnText('name', 'Name')
            ->setSortable(true)
            ->setFilterText()
            ->setPlaceholder('Search by Name');

        $grid->addColumnText('surname', 'Surname')
            ->setSortable(true)
            ->setFilterText()
            ->setPlaceholder('Search by Surname');

        $grid->addAction('edit', 'Edit', 'Example:edit')
            ->setTitle('Edit')
            ->setIcon('edit');

        $onClick = function($id) use ($grid) : void {
            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->find($id);

            $this->em->remove($userEntity);
            $this->em->flush();

            $this->flashMessage('REMOVED');
            $this->redrawControl('flashes');

            $grid->reload();
        };

        $grid->addActionCallback('delete', 'Delete')
            ->setConfirmation(
                new CallbackConfirmation(
                    function(UserEntity $userEntity) : string {
                        return 'Are you sure that you want to delete User#' . $userEntity->id . ' with username ' . $userEntity->username . '?';
                    }
                )
            )
            ->setTitle('Delete')
            ->setIcon('trash')
            ->onClick[] = $onClick;



        $inlineEdit = $grid->addInlineEdit()
            ->setText('Editovat')
            ->setTitle('Editovat Kategorii');

        $setDefaultsCallback = function(Container $container, UserEntity $userEntity) : void {
            $container->setDefaults(
                [
                    'name' => $userEntity->name,
                    'surname' => $userEntity->surname,
                ]
            );
        };

        $onSubmitEdit = function($id, ArrayHash $values) use ($grid) : void {
            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->findOneBy(
                    [
                        'id' => $id,
                    ]
                );

            $userEntity->name = $values->name;
            $userEntity->surname = $values->surname;

            $userEntity->updatedAt = new DateTimeImmutable();

            try {
                $this->em->persist($userEntity);
                $this->em->flush();
            }  catch (DbalException $exception) {
                $this->flashMessage($exception->getMessage());
            }

            $this->flashMessage('Kategorie ' . $userEntity->name . ' uložena.', 'success');
        };

        $inlineEdit->onControlAdd[] = function (Container $container) use ($grid) : void {
            $container->addText('name');
            $container->addText('surname');
        };
        $inlineEdit->onSetDefaults[] = $setDefaultsCallback;
        $inlineEdit->onSubmit[] = $onSubmitEdit;

        return $grid;
    }

}
