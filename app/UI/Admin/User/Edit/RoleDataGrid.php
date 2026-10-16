<?php declare(strict_types=1);

namespace App\UI\Admin\User\Edit;

use stdClass;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Contributte\Datagrid\Datagrid;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\QueryBuilder;
use Nette\Localization\Translator;
use App\Database\EntityManagerDecorator;

class RoleDataGrid
{
    private Datagrid $grid;

    private UserEntity $userEntity;

    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly Translator             $translator,
    )
    {
        $this->grid = new Datagrid();
    }

    public function setUserEntity(UserEntity $userEntity) : RoleDataGrid
    {
        $this->userEntity = $userEntity;

        return $this;
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
            ->addColumnNumber('id', 'admin-user-edit.roleGrid.role.id')
            ->setDefaultHide(true)
            ->setSortable(true)
            ->setFilterText()
            ->setPlaceholder('admin-user-list.id.search');

        $this->grid
            ->addColumnText('name', 'admin-user-edit.roleGrid.role.name')
            ->setRenderer(
                function(RoleEntity $roleEntity) : string {
                    return $roleEntity->name;
                }
            )
            ->setSortable(true)
            ->setSortableCallback(
                function(QueryBuilder $queryBuilder, array $sort) : void {
                    $queryBuilder->orderBy('_role.name', $sort['name'] === 'DESC' ? 'DESC' : 'ASC');
                }
            )
            ->setFilterText()
            ->setCondition(
                function(QueryBuilder $queryBuilder, $role) : void {
                    $queryBuilder->andWhere('REGEXP(_role.name, :roleName) = true')
                        ->setParameter('roleName', $role);
                }
            )
            ->setPlaceholder('admin-user-edit.roleGrid.role.search');
    }

    private function createActions() : void
    {
        $addRole = function(string $roleId) {
            /**
             * @var ?RoleEntity $roleEntity
             */
            $roleEntity = $this->em
                ->getRepository(RoleEntity::class)
                ->findOneBy(
                    [
                        'id' => $roleId,
                    ]
                );

            if ($roleEntity === null) {
                $this->grid->error('role not found');
            }

            $this->userEntity->addRoleEntity($roleEntity);
            $this->userEntity->updatedAt = new DateTimeImmutable();

            try {
                $this->em->persist($this->userEntity);
                $this->em->flush();

                $flash = new stdClass();
                $flash->place = 'general';
                $flash->type = 'success';
                $flash->message = $this->translator->translate('admin-user-edit.roleGrid.role.added');

                $this->grid->getPresenter()->flashMessage($flash);
                $this->grid->getPresenter()->redrawControl('flashes');
                $this->grid->reload();
            } catch (DbalException $exception) {
                bdump($exception);
                $flash = new stdClass();
                $flash->place = 'general';
                $flash->type = 'danger';
                $flash->message = $this->translator->translate('messages.operationFailed');

                $this->grid->getPresenter()->flashMessage($flash);
                $this->grid->getPresenter()->redrawControl('flashes');
            }
        };

        $this->grid
            ->addActionCallback('addRole', 'admin-user-edit.roleGrid.addRole')
            ->setIcon('plus')
            ->onClick[] = $addRole;

        $this->grid->allowRowsAction(
            'addRole',
            function(RoleEntity $roleEntity) : bool {
                return !$this->userEntity->roles->contains($roleEntity);
            }
        );

        $closure = function (string $roleId) {
            /**
             * @var ?RoleEntity $roleEntity
             */
            $roleEntity = $this->em
                ->getRepository(RoleEntity::class)
                ->findOneBy(
                    [
                        'id' => $roleId,
                    ]
                );

            if ($roleEntity === null) {
                $this->grid->error('role not found');
            }

            $this->userEntity->removeRoleEntity($roleEntity);
            $this->userEntity->updatedAt = new DateTimeImmutable();

            try {
                $this->em->persist($this->userEntity);
                $this->em->flush();

                $flash = new stdClass();
                $flash->place = 'general';
                $flash->type = 'success';
                $flash->message = $this->translator->translate('admin-user-edit.roleGrid.role.removed');

                $this->grid->getPresenter()->flashMessage($flash);
                $this->grid->getPresenter()->redrawControl('flashes');
                $this->grid->reload();
            } catch (DbalException $exception) {
                bdump($exception);
                $flash = new stdClass();
                $flash->place = 'general';
                $flash->type = 'danger';
                $flash->message = $this->translator->translate('messages.operationFailed');

                $this->grid->getPresenter()->flashMessage($flash);
                $this->grid->getPresenter()->redrawControl('flashes');
            }
        };
        $removeRole = $closure;

        $this->grid
            ->addActionCallback('deleteRole', 'admin-user-edit.roleGrid.removeRole')
            ->setClass('btn btn-sm btn-danger')
            ->setIcon('trash')
            ->onClick[] = $removeRole;

        $this->grid->allowRowsAction(
            'deleteRole',
            function(RoleEntity $roleEntity) : bool {
                return $this->userEntity->roles->contains($roleEntity);
            }
        );
    }

}
