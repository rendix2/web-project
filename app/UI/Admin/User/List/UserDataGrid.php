<?php declare(strict_types=1);

namespace App\UI\Admin\User\List;

use App\Forms\EmailFormControl;
use App\Forms\EmailFormControlFactory;
use App\Forms\UsernameFormControl;
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
                    $queryBuilder->orderBy('_user.surname', $sort['fullName'] === 'DESC' ? 'DESC' : 'ASC')
                        ->addOrderBy('_user.name', $sort['fullName'] === 'DESC' ? 'DESC' : 'ASC');
                }
            )
            ->setFilterText()
            ->setCondition(
                function(QueryBuilder $queryBuilder, $fullName) : void {
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()
                            ->orX(
                                $queryBuilder->expr()->eq('REGEXP(_user.name, :fullName)', 'true'),
                                $queryBuilder->expr()->eq('REGEXP(_user.surname, :fullName)', 'true'),
                                $queryBuilder->expr()->eq('REGEXP(CONCAT(_user.name, \' \', _user.surname), :fullName)', 'true'),
                                $queryBuilder->expr()->eq('REGEXP(CONCAT(_user.surname, \' \', _user.name), :fullName)', 'true'),
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
                $this->grid->getPresenter()->error('user not found');
            }

            try {
                $this->em->remove($userEntity);
                $this->em->flush();

                $this->grid->getPresenter()->flashMessage(
                    $this->translator->translate('admin-user-list.delete.success', ['username' => $userEntity->username]),
                    'success'
                );
                $this->grid->getPresenter()->redrawControl('flashes');

                $this->grid->reload();
            } catch (DbalException $exception) {
                bdump($exception);
                $this->grid->getPresenter()->flashMessage($this->translator->translate('messages.operationFailed'), 'danger');
                $this->grid->getPresenter()->redrawControl('flashes');
            }
        };

        $this->grid
            ->addActionCallback('delete', 'admin-user-list.delete.name')
            ->setConfirmation(
                new CallbackConfirmation(
                    function(UserEntity $userEntity) : string {
                        return (string) $this->translator->translate('admin-user-list.delete.confirm', ['username' => $userEntity->username]);
                    }
                )
            )
            ->setClass('btn btn-sm btn-danger ajax')
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
            $usernameControl = $container->getComponent('username');
            $emailControl = $container->getComponent('email');

            if ($usernameControl instanceof UsernameFormControl && $emailControl instanceof EmailFormControl) {
                $usernameControl->setExcludeUserId($userEntity->id);
                $emailControl->setExcludeUserId($userEntity->id);
            }

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
                $this->grid->getPresenter()->flashMessage(
                    $this->translator->translate('admin-user-list.form.fullName.noSpace'),
                    'danger'
                );
                $this->grid->getPresenter()->redrawControl('flashes');

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
                $this->grid->getPresenter()->error('user not found');
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
            $userEntity->isActive = (bool) $values->isActive;

            if (!$userEntity->isActive) {
                // a deactivated user keeps nothing that logs him in (his session ends at the next request, AutoLoginService)
                $userEntity->autoLogins->clear();
            }
            $userEntity->updatedAt = new DateTimeImmutable();

            try {
                $this->em->persist($userEntity);
                $this->em->flush();

                $this->grid->getPresenter()->flashMessage(
                    $this->translator->translate('admin-user-list.edit.success', ['username' => $userEntity->username]),
                    'success'
                );
                $this->grid->getPresenter()->redrawControl('flashes');
                $this->grid->reload();
            } catch (DbalException $exception) {
                bdump($exception);
                $this->grid->getPresenter()->flashMessage($this->translator->translate('messages.operationFailed'), 'danger');
                $this->grid->getPresenter()->redrawControl('flashes');
            }
        };

        $inlineEdit->onControlAdd[] = function(Container $container) : void {
            $container->setMappedType(UserInlineEditValues::class);

            $inlineEditPost = $this->grid->getPresenter()->getHttpRequest()->getPost('inline_edit');
            $uuid = is_array($inlineEditPost) ? ($inlineEditPost['_id'] ?? null) : null;
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

            $container->addSelect(
                'isActive',
                items: [
                    0 => (string) $this->translator->translate('messages.no'),
                    1 => (string) $this->translator->translate('messages.yes'),
                ]
            )
                ->setPrompt((string) $this->translator->translate('messages.select'));
        };

        $inlineEdit->onSetDefaults[] = $setDefaultsCallback;
        $inlineEdit->onSubmit[] = $onSubmitEdit;
    }

}
