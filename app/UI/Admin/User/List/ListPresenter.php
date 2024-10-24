<?php declare(strict_types=1);

namespace App\UI\Admin\User\List;

use App\Model\Entity\UserEntity;
use Contributte\Datagrid\Column\Action\Confirmation\CallbackConfirmation;
use Contributte\Datagrid\Datagrid;
use Contributte\Translation\Translator;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\QueryBuilder;
use Nette\Application\UI\Presenter;
use Nette\Forms\Container;
use Nette\Utils\ArrayHash;
use Nettrine\ORM\EntityManagerDecorator;

class ListPresenter extends Presenter
{
    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly Translator             $translator,
    )
    {
    }

    public function createComponentGrid(string $name) : Datagrid
    {
        $dataSource = $this->em
            ->getRepository(UserEntity::class)
            ->createQueryBuilder('_user');

        $grid = new Datagrid();

        $grid->setDataSource($dataSource);
        $grid->setDefaultPerPage(10);
        $grid->setTranslator($this->translator);
        $grid->setColumnsHideable();

        $grid->addColumnNumber('id', 'admin-user-list.id.name')
            ->setDefaultHide(true)
            ->setSortable(true)
            ->setFilterText()
            ->setPlaceholder('admin-user-list.id.search');

        $grid->addColumnText('fullName', 'admin-user-list.fullName.name')
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

        $grid->addColumnText('username', 'admin-user-list.userName.name')
            ->setSortable(true)
            ->setFilterText()
            ->setPlaceholder('admin-user-list.userName.search');

        $grid->addAction('edit', 'admin-user-list.edit.name', ':Admin:User:Edit:default')
            ->setTitle('admin-user-list.edit.title')
            ->setIcon('edit');

        $onClick = function($id) use ($grid) : void {
            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->findOneBy(
                    [
                        'id' => $id,
                    ]
                );

            $this->em->remove($userEntity);
            $this->em->flush();

            $this->flashMessage(
                $this->translator->translate('admin-user-list.delete.success', ['username' => $userEntity->username]),
                'success'
            );
            $this->redrawControl('flashes');

            $grid->reload();
        };

        $grid->addActionCallback('delete', 'admin-user-list.delete.name')
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

        $inlineEdit = $grid->addInlineEdit()
            ->setText($this->translator->translate('admin-user-list.edit.inline.name'))
            ->setTitle('admin-user-list.edit.inline.title');

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

                $this->flashMessage(
                    $this->translator->translate('admin-user-list.edit.success', ['username' => $userEntity->username]),
                    'success'
                );
                $this->redrawControl('flashes');
            } catch (DbalException $exception) {
                $this->flashMessage($exception->getMessage(), 'danger');
                $this->redrawControl('flashes');
            }
        };

        $inlineEdit->onControlAdd[] = function(Container $container) use ($grid) : void {
            $container->addText('name');
            $container->addText('surname');
        };
        $inlineEdit->onSetDefaults[] = $setDefaultsCallback;
        $inlineEdit->onSubmit[] = $onSubmitEdit;

        return $grid;
    }

}
