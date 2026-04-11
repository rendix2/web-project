<?php declare(strict_types=1);

namespace App\UI\Admin\User\List;

use App\Forms\EmailFormControlFactory;
use App\Forms\UsernameFormControlFactory;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Facade\UserFacade;
use Contributte\Datagrid\Column\Action\Confirmation\CallbackConfirmation;
use Contributte\Datagrid\Datagrid;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\QueryBuilder;
use Nette\Forms\Container;
use Nette\Localization\Translator;
use App\Database\EntityManagerDecorator;

class UserDataGrid
{
    private Datagrid $grid;

    public function __construct(
        private readonly EntityManagerDecorator     $em,
        private readonly Translator                 $translator,
        private readonly UsernameFormControlFactory $usernameFormControlFactory,
        private readonly EmailFormControlFactory    $emailFormControlFactory,
    )
    {
        $this->grid = new Datagrid();
    }

    public function create() : Datagrid
    {
        $this->createColumns();
        $this->createActions();
        $this->createInlineEdit();

        return $this->grid;
    }

    private function createDataSource() : QueryBuilder
    {
        return $this->em
            ->getRepository(UserEntity::class)
            ->createQueryBuilder('_user');
    }

    private function createColumns() : void
    {
        $this->grid->setPrimaryKey('uuid');
        $this->grid->setDefaultPerPage(10);
        $this->grid->setTranslator($this->translator);
        $this->grid->setColumnsHideable();
        $this->grid->setDataSource($this->createDataSource());

        $this->grid
            ->addColumnNumber('id', 'admin-user-list.id.name')
            ->setDefaultHide(true)
            ->setSortable(true)
            ->setFilterText()
            ->setPlaceholder('admin-user-list.id.search');

        $this->grid
            ->addColumnText('fullName', 'admin-user-list.fullName.name')
            ->setRenderer(
                function(UserEntity $userEntity) : string {
                    return $userEntity->name . ' ' . $userEntity->surname;
                }
            )
            ->setSortable(true)
            ->setSortableCallback(
                function(QueryBuilder $queryBuilder, array $sort) : void {
                    $queryBuilder->orderBy('_user.surname', $sort['fullName'])
                        ->addOrderBy('_user.name', $sort['fullName']);
                }
            )
            ->setFilterText()
            ->setCondition(
                function(QueryBuilder $queryBuilder, $fullName) : void {
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()
                            ->orX(
                                $queryBuilder->expr()->eq('REGEXP(_user.name, :fullName)', true),
                                $queryBuilder->expr()->eq('REGEXP(_user.surname, :fullName)', true),
                                $queryBuilder->expr()->eq('REGEXP(CONCAT(_user.name, \' \', _user.surname), :fullName)', true),
                                $queryBuilder->expr()->eq('REGEXP(CONCAT(_user.surname, \' \', _user.name), :fullName)', true),
                            )
                    )
                        ->setParameter('fullName', $fullName);
                }
            )
            ->setPlaceholder('admin-user-list.fullName.search');

        $this->grid
            ->addColumnText('username', 'admin-user-list.userName.name')
            ->setSortable(true)
            ->setFilterText()
            ->setPlaceholder('admin-user-list.userName.search');

        $this->grid
            ->addColumnText('email', 'admin-user-list.email.name')
            ->setSortable(true)
            ->setFilterText()
            ->setPlaceholder('admin-user-list.email.search');

        $this->grid
            ->addColumnText('isActive', 'admin-user-list.isActive.name')
            ->setSortable()
            ->setReplacement(
                [
                    0 => $this->translator->translate('messages.no'),
                    1 => $this->translator->translate('messages.yes'),
                ]
            )
            ->setFilterSelect(
                [
                    0 => $this->translator->translate('messages.no'),
                    1 => $this->translator->translate('messages.yes'),
                ]
            )
            ->setPrompt((string) $this->translator->translate('messages.select'));
    }

    private function createActions() : void
    {
        $this->grid
            ->addAction('edit', 'admin-user-list.edit.name', ':Admin:User:Edit:default')
            ->setTitle('admin-user-list.edit.title')
            ->setIcon('edit');

        $onClick = function(string $uuid) : void {
            /**
             * @var ?UserEntity $userEntity
             */
            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->findOneBy(
                    [
                        'uuid' => $uuid,
                    ]
                );

            if ($userEntity === null) {
                $this->grid->presenter->error('user not found');
            }

            try {
                $this->em->remove($userEntity);
                $this->em->flush();

                $this->grid->presenter->flashMessage(
                    $this->translator->translate('admin-user-list.delete.success', ['username' => $userEntity->username]),
                    'success'
                );
                $this->grid->presenter->redrawControl('flashes');

                $this->grid->reload();
            } catch (DbalException $exception) {
                $this->grid->presenter->flashMessage($exception->getMessage(), 'danger');
                $this->grid->presenter->redrawControl('flashes');
            }
        };

        $this->grid
            ->addActionCallback('delete', 'admin-user-list.delete.name')
            ->setConfirmation(
                new CallbackConfirmation(
                    function(UserEntity $userEntity) : string {
                        return 'Are you sure that you want to delete User#' . $userEntity->id . ' with username ' . $userEntity->username . '?';
                    }
                )
            )
            ->setClass('btn btn-xs btn-default btn-danger btn-block ajax')
            ->setTitle('admin-user-list.delete.title')
            ->setIcon('trash')
            ->onClick[] = $onClick;

        $allowDelete = function(UserEntity $userEntity) : bool {
            return $userEntity->id !== (string)$this->grid->getPresenter()->getUser()->id;
        };

        $this->grid->allowRowsAction('delete', $allowDelete);
    }

    private function createInlineEdit() : void
    {
        $inlineEdit = $this->grid->addInlineEdit()
            ->setText((string) $this->translator->translate('admin-user-list.edit.inline.name'))
            ->setTitle('admin-user-list.edit.inline.title');

        $setDefaultsCallback = function(Container $container, UserEntity $userEntity) : void {
            $container->getComponent('username')->setExcludeUserId($userEntity->id);
            $container->getComponent('email')->setExcludeUserId($userEntity->id);

            $container->setDefaults(
                [
                    'fullName' => $userEntity->name . ' ' . $userEntity->surname,
                    'username' => $userEntity->username,
                    'email' => $userEntity->email,
                    'isActive' => (int) $userEntity->isActive,
                ]
            );
        };

        $onSubmitEdit = function(string $uuid, UserInlineEditValues $values) : void {
            if (substr_count($values->fullName, ' ') === 0) {
                $this->grid->presenter->flashMessage(
                    $this->translator->translate('admin-user-list.form.fullName.noSpace'),
                    'danger'
                );
                $this->grid->presenter->redrawControl('flashes');

                return;
            }

            /**
             * @var ?UserEntity $userEntity
             */
            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->findOneBy(
                    [
                        'uuid' => $uuid,
                    ]
                );

            if ($userEntity === null) {
                $this->grid->presenter->error('user not found');
            }

            if ($userEntity->email !== $values->email) {
                $userEmailEntity = new UserEmailEntity();
                $userEmailEntity->email = $values->email;
                $userEmailEntity->user = $userEntity;

                $userEntity->addUserEmailEntity($userEmailEntity);
            }

            [$name, $surname] = explode(' ', $values->fullName, 2);

            $userEntity->name = $name;
            $userEntity->surname = $surname;
            $userEntity->username = $values->username;
            $userEntity->email = $values->email;
            $userEntity->isActive = $values->isActive;
            $userEntity->updatedAt = new DateTimeImmutable();

            try {
                $this->em->persist($userEntity);
                $this->em->flush();

                $this->grid->presenter->flashMessage(
                    $this->translator->translate('admin-user-list.edit.success', ['username' => $userEntity->username]),
                    'success'
                );
                $this->grid->presenter->redrawControl('flashes');
                $this->grid->reload();
                bdump('OK');
            } catch (DbalException $exception) {
                $this->grid->presenter->flashMessage($exception->getMessage(), 'danger');
                $this->grid->presenter->redrawControl('flashes');
                bdump('FAIL');
            }
        };

        $inlineEdit->onControlAdd[] = function(Container $container) : void {
            $uuid = $this->grid->getPresenter()->getHttpRequest()->getPost('inline_edit')['_id'] ?? null;
            $userId = null;

            if ($uuid !== null) {
                $user = $this->em
                    ->getRepository(UserEntity::class)
                    ->findOneBy(
                        [
                            'uuid' => $uuid
                        ]
                    );

                if ($user !== null) {
                    $userId = $user->id;
                }
            }

            $username = $this->usernameFormControlFactory->create('Username');
            $email = $this->emailFormControlFactory->create('Email');

            if ($userId !== null) {
                $username->setExcludeUserId($userId);
            }

            if ($userId !== null) {
                $email->setExcludeUserId($userId);
            }

            $container->addText('fullName')
                ->setRequired();

            $container->addComponent($username, 'username');
            $container->addComponent($email, 'email');

            $container->addSelect('isActive', items: [0 => 'no', 1 => 'yes'])
                ->setPrompt('select');
        };

        $inlineEdit->onSetDefaults[] = $setDefaultsCallback;
        $inlineEdit->onSubmit[] = $onSubmitEdit;
    }

}
