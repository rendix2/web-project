<?php declare(strict_types=1);

namespace App\UI\Admin\User\Edit;

use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Contributte\Datagrid\Datagrid;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\QueryBuilder;
use Nette\Localization\Translator;
use Nettrine\ORM\EntityManagerDecorator;

class RolesDataGridFactory
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

        return $this->grid;
    }

    private function createDataSource() : QueryBuilder
    {
        return $this->em
            ->getRepository(RoleEntity::class)
            ->createQueryBuilder('_role');
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
            ->addColumnText('name', 'Name')
            ->setRenderer(
                function(RoleEntity $roleEntity) : string {
                    return $roleEntity->name;
                }
            )
            ->setSortable(true)
            ->setSortableCallback(
                function(QueryBuilder $queryBuilder, array $sort) : void {
                    $queryBuilder->orderBy('_role.name', $sort['name']);
                }
            )
            ->setFilterText()
            ->setCondition(
                function(QueryBuilder $queryBuilder, $role) : void {
                    $queryBuilder->andWhere('_role.name')
                        ->setParameter('role', $role);
                }
            )
            ->setPlaceholder('admin-user-list.fullName.search');
    }

    private function createActions() : void
    {
        $addRole = function(string $roleId) {
            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->findOneBy(
                    [
                        'id' => $this->grid->presenter->getUser()->getId(),
                    ]
                );

            if (!$userEntity) {
                $this->grid->presenter->error('user not found');
            }

            $roleEntity = $this->em
                ->getRepository(RoleEntity::class)
                ->findOneBy(
                    [
                        'id' => $roleId,
                    ]
                );

            if (!$roleEntity) {
                $this->grid->error('role not found');
            }

            $userEntity->addRoleEntity($roleEntity);
            $userEntity->updatedAt = new DateTimeImmutable();

            try {
                $this->em->persist($userEntity);
                $this->em->flush();

                $this->grid->presenter->flashMessage('role added', 'success');
                $this->grid->presenter->redrawControl('flashes');
                $this->grid->reload();
            } catch (DbalException $exception) {
                $this->grid->presenter->flashMessage($exception->getMessage(), 'danger');
                $this->grid->presenter->redrawControl('flashes');
            }
        };

        $this->grid
            ->addActionCallback('addRole', 'admin-user-edit.roleGrid.addRole')
            ->setIcon('plus')
            ->onClick[] = $addRole;

        $this->grid->allowRowsAction(
            'addRole',
            function(RoleEntity $roleEntity) : bool {
                $userEntity = $this->em
                    ->getRepository(UserEntity::class)
                    ->findOneBy(
                        [
                            'id' => $this->grid->presenter->getUser()->getId(),
                        ]
                    );

                if (!$userEntity) {
                    $this->grid->presenter->error('user not found');
                }

                return !$userEntity->roles->contains($roleEntity);
            }
        );

        $removeRole = function(string $roleId) {
            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->findOneBy(
                    [
                        'id' => $this->grid->presenter->getUser()->getId(),
                    ]
                );

            if (!$userEntity) {
                $this->grid->presenter->error('user not found');
            }

            $roleEntity = $this->em
                ->getRepository(RoleEntity::class)
                ->findOneBy(
                    [
                        'id' => $roleId,
                    ]
                );

            if (!$roleEntity) {
                $this->grid->error('role not found');
            }

            $userEntity->removeRoleEntity($roleEntity);
            $userEntity->updatedAt = new DateTimeImmutable();

            try {
                $this->em->persist($userEntity);
                $this->em->flush();

                $this->grid->presenter->flashMessage('role removed', 'success');
                $this->grid->presenter->redrawControl('flashes');
                $this->grid->reload();
            } catch (DbalException $exception) {
                $this->grid->presenter->flashMessage($exception->getMessage(), 'danger');
                $this->grid->presenter->redrawControl('flashes');
            }
        };

        $this->grid
            ->addActionCallback('deleteRole', 'admin-user-edit.roleGrid.removeRole')
            ->setClass('btn btn-xs btn-default btn-danger')
            ->setIcon('trash')
            ->onClick[] = $removeRole;

        $this->grid->allowRowsAction(
            'deleteRole',
            function(RoleEntity $roleEntity) : bool {
                $userEntity = $this->em
                    ->getRepository(UserEntity::class)
                    ->findOneBy(
                        [
                            'id' => $this->grid->presenter->getUser()->getId(),
                        ]
                    );

                if (!$userEntity) {
                    $this->grid->presenter->error('user not found');
                }

                return $userEntity->roles->contains($roleEntity);
            }
        );
    }

}
