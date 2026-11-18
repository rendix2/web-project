<?php declare(strict_types=1);

namespace App\UI\Admin\Role\List;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\UI\Grid\IdFilterCondition;
use App\UI\Grid\SecuredDatagrid;
use Contributte\Datagrid\Column\Action\Confirmation\CallbackConfirmation;
use Contributte\Datagrid\Datagrid;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\QueryBuilder;
use Nette\Forms\Container;
use Nette\Localization\Translator;
use stdClass;

/**
 * class RoleGrid
 *
 * @package App\UI\Admin\Role\List
 */
class RoleDataGrid
{
    private SecuredDatagrid $grid;

    public function __construct(
        private readonly EntityManagerDecorator     $em,
        private readonly Translator                 $translator,
    )
    {
        $this->grid = new SecuredDatagrid();
    }

    public function create() : Datagrid
    {
        // a new grid for every create(): the service is shared in the container, a second create() (a second request of the same
        // container, a forward) added the columns to the first grid again ("There is already column at key [id] defined")
        $this->grid = new SecuredDatagrid();

        $this->createColumns();
        $this->createActions();
        $this->createInlineEdit();

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
        $this->grid->setPrimaryKey('id');
        $this->grid->setDefaultPerPage(10);
        $this->grid->setTranslator($this->translator);
        $this->grid->setColumnsHideable();
        $this->grid->setDataSource($this->createDataSource());

        $this->grid
            ->addColumnNumber('id', 'admin-role-list.id.name')
            ->setDefaultHide(true)
            ->setSortable(true)
            ->setFilterText()
            ->setCondition(new IdFilterCondition('_role.id'))
            ->setPlaceholder('admin-role-list.id.search');

        $this->grid
            ->addColumnText('name', 'admin-role-list.name.name')
            ->setRenderer(
                fn (RoleEntity $roleEntity) : string => $roleEntity->name
            )
            ->setSortable(true)
            ->setSortableCallback(
                function (QueryBuilder $queryBuilder, array $sort) : void {
                    $queryBuilder->orderBy('_role.name', $sort['name'] === 'DESC' ? 'DESC' : 'ASC');
                }
            )
            ->setFilterText()
            ->setCondition(
                function (QueryBuilder $queryBuilder, $name) : void {
                    $queryBuilder->andWhere('REGEXP(_role.name, :roleName) = true')
                        ->setParameter('roleName', $name);
                }
            )
            ->setPlaceholder('admin-role-list.name.search');
    }

    private function createActions() : void
    {
        $this->grid
            ->addAction('edit', 'admin-role-list.edit.name', ':Admin:Role:Edit:default')
            ->setTitle('admin-role-list.edit.title')
            ->setIcon('edit');

        $onClick = function (string $id) : void {
            /**
             * @var ?RoleEntity $roleEntity
             */
            $roleEntity = $this->em
                ->getRepository(RoleEntity::class)
                ->findOneBy(
                    [
                        'id' => $id,
                    ]
                );

            if ($roleEntity === null) {
                $this->grid->getPresenter()->error('Role not found');
            }

            try {
                $this->em->remove($roleEntity);
                $this->em->flush();

                $flash = new stdClass();
                $flash->place = 'general';
                $flash->type = 'success';
                $flash->message = $this->translator->translate('admin-role-list.delete.success', ['name' => $roleEntity->name]);

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
            ->addActionCallback('delete', 'admin-role-list.delete.name')
            ->setConfirmation(
                new CallbackConfirmation(
                    fn (RoleEntity $roleEntity) : string => (string) $this->translator->translate('admin-role-list.delete.confirm', ['name' => $roleEntity->name])
                )
            )
            ->setClass('btn btn-sm btn-danger ajax')
            ->setTitle('admin-role-list.delete.title')
            ->setIcon('trash')
            ->onClick[] = $onClick;
    }

    private function createInlineEdit() : void
    {
        $inlineEdit = $this->grid->addInlineEdit()
            ->setText((string) $this->translator->translate('admin-role-list.edit.inline.name'))
            ->setTitle('admin-role-list.edit.inline.title');

        $setDefaultsCallback = function (Container $container, RoleEntity $roleEntity) : void {
            $container->setDefaults(
                [
                    'name' => $roleEntity->name,
                ]
            );
        };

        $onSubmitEdit = function (string $id, RoleInlineEditValues $values) : void {
            /**
             * @var ?RoleEntity $roleEntity
             */
            $roleEntity = $this->em
                ->getRepository(RoleEntity::class)
                ->findOneBy(
                    [
                        'id' => $id,
                    ]
                );

            if ($roleEntity === null) {
                $this->grid->getPresenter()->error('Role not found');
            }

            $roleEntity->name = $values->name;

            try {
                $this->em->persist($roleEntity);
                $this->em->flush();

                $flash = new stdClass();
                $flash->place = 'general';
                $flash->type = 'success';
                $flash->message = $this->translator->translate('admin-role-list.edit.success', ['name' => $roleEntity->name]);

                $this->grid->getPresenter()->flashMessage($flash);
                $this->grid->getPresenter()->redrawControl('flashes');
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

        $inlineEdit->onControlAdd[] = function (Container $container) : void {
            $container->setMappedType(RoleInlineEditValues::class);

            $container->addText('name')
                ->setRequired();
        };

        $inlineEdit->onSetDefaults[] = $setDefaultsCallback;
        $inlineEdit->onSubmit[] = $onSubmitEdit;
    }
}
