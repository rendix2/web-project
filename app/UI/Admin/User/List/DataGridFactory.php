<?php

namespace App\UI\Admin\User\List;

use App\Model\Entity\UserEntity;
use Contributte\Datagrid\Column\Action\Confirmation\CallbackConfirmation;
use Contributte\Datagrid\Datagrid;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\QueryBuilder;
use Nette\Forms\Container;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;
use Nettrine\ORM\EntityManagerDecorator;

class DataGridFactory
{
    private Datagrid $grid;

    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly Translator             $translator,
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
        $this->grid->setDataSource($this->createDataSource());
        $this->grid->setDefaultPerPage(10);
        $this->grid->setTranslator($this->translator);
        $this->grid->setColumnsHideable();

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
            ->setPrompt($this->translator->translate('messages.select'));
    }

    private function createActions() : void
    {
        $this->grid
            ->addAction('edit', 'admin-user-list.edit.name', ':Admin:User:Edit:default')
            ->setTitle('admin-user-list.edit.title')
            ->setIcon('edit');

        $onClick = function($id) : void {
            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->findOneBy(
                    [
                        'id' => $id,
                    ]
                );

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
    }

    private function createInlineEdit() : void
    {
        $inlineEdit = $this->grid->addInlineEdit()
            ->setText($this->translator->translate('admin-user-list.edit.inline.name'))
            ->setTitle('admin-user-list.edit.inline.title');

        $setDefaultsCallback = function(Container $container, UserEntity $userEntity) : void {
            $container->setDefaults(
                [
                    'fullName' => $userEntity->name . ' ' . $userEntity->surname,
                    'username' => $userEntity->username,
                    'email' => $userEntity->email,
                    'isActive' => (int) $userEntity->isActive,
                ]
            );
        };

        $onSubmitEdit = function($id, ArrayHash $values) : void {
            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->findOneBy(
                    [
                        'id' => $id,
                    ]
                );

            if ($userEntity->username !== $values->username) {
                $usernameExists = $this->em
                    ->getRepository(UserEntity::class)
                    ->findOneBy(
                        [
                            'username' => $values->username
                        ]
                    );

                if ($usernameExists) {
                    $this->grid->presenter->flashMessage(
                        $this->translator->translate('admin-user-edit.form.username.exists', ['username' => $values->username]),
                        'danger'
                    );

                    $this->grid->presenter->redrawControl('flashes');

                    return;
                }
            }

            if ($userEntity->email !== $values->email) {
                $emailExists = $this->em
                    ->getRepository(UserEntity::class)
                    ->findOneBy(
                        [
                            'email' => $values['email']
                        ]
                    );

                if ($emailExists) {
                    $this->grid->presenter->flashMessage(
                        $this->translator->translate('admin-user-edit.form.email.exists', ['email' => $values->email]),
                        'danger'
                    );
                    $this->grid->presenter->redrawControl('flashes');

                    return;
                }
            }

            if (!substr_count($values->fullName, ' ')) {
                $this->grid->presenter->flashMessage(
                    $this->translator->translate('admin-user-list.form.fullName.noSpace'),
                    'danger'
                );
                $this->grid->presenter->redrawControl('flashes');

                return;
            }

            [$name, $surname] = explode(' ', $values->fullName);

            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->findOneBy(
                    [
                        'id' => $id,
                    ]
                );

            $userEntity->name = $name;
            $userEntity->surname = $surname;
            $userEntity->username = $values->username;
            $userEntity->email = $values->email;
            $userEntity->isActive = (bool) $values->isActive;
            $userEntity->updatedAt = new DateTimeImmutable();

            try {
                $this->em->persist($userEntity);
                $this->em->flush();

                $this->grid->presenter->flashMessage(
                    $this->translator->translate('admin-user-list.edit.success', ['username' => $userEntity->username]),
                    'success'
                );
                $this->grid->presenter->redrawControl('flashes');
            } catch (DbalException $exception) {
                $this->grid->presenter->flashMessage($exception->getMessage(), 'danger');
                $this->grid->presenter->redrawControl('flashes');
            }
        };

        $inlineEdit->onControlAdd[] = function(Container $container) : void {
            $container->addText('fullName')
                ->setRequired();

            $container->addText('username')
                ->setRequired('admin-user-edit.form.username.required')
                ->setMaxLength(512);

            $container->addEmail('email')
                ->setRequired('admin-user-edit.form.email.required')
                ->setMaxLength(1024);

            $container->addSelect('isActive', items: [0 => 'no', 1 => 'yes'])
                ->setPrompt('select');
        };
        $inlineEdit->onSetDefaults[] = $setDefaultsCallback;
        $inlineEdit->onSubmit[] = $onSubmitEdit;
    }

}
