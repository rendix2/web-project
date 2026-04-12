<?php declare(strict_types=1);

namespace App\UI\Admin\Role\List;

use App\Database\EntityManagerDecorator;
use App\Forms\EmailFormControlFactory;
use App\Forms\UsernameFormControlFactory;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\UI\Admin\User\List\UserInlineEditValues;
use Contributte\Datagrid\Column\Action\Confirmation\CallbackConfirmation;
use Contributte\Datagrid\Datagrid;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\QueryBuilder;
use Nette\Forms\Container;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;

/**
 * class RoleGrid
 *
 * @package App\UI\Admin\Role\List
 */
class RoleDataGrid
{

    private Datagrid $grid;

    public function __construct(
        private readonly EntityManagerDecorator     $em,
        private readonly Translator                 $translator,
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
            ->setPlaceholder('admin-role-list.id.search');

        $this->grid
            ->addColumnText('name', 'admin-role-list.name.name')
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
                function(QueryBuilder $queryBuilder, $name) : void {
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

        $onClick = function(string $id) : void {
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
                $this->grid->presenter->error('Role not found');
            }

            try {
                $this->em->remove($roleEntity);
                $this->em->flush();

                $this->grid->presenter->flashMessage(
                    $this->translator->translate('admin-role-list.delete.success', ['name' => $roleEntity->name]),
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
            ->addActionCallback('delete', 'admin-role-list.delete.name')
            ->setConfirmation(
                new CallbackConfirmation(
                    function(RoleEntity $roleEntity) : string {
                        return 'Are you sure that you want to delete Role#' . $roleEntity->id . ' with name ' . $roleEntity->name . '?';
                    }
                )
            )
            ->setClass('btn btn-xs btn-default btn-danger btn-block ajax')
            ->setTitle('admin-role-list.delete.title')
            ->setIcon('trash')
            ->onClick[] = $onClick;

        /*
        $allowDelete = function(RoleEntity $roleEntity) : bool {
            return $roleEntity->id !== (string)$this->grid->getPresenter()->getUser()->id;
        };

        $this->grid->allowRowsAction('delete', $allowDelete);
        */
    }

    private function createInlineEdit() : void
    {
        $inlineEdit = $this->grid->addInlineEdit()
            ->setText((string) $this->translator->translate('admin-role-list.edit.inline.name'))
            ->setTitle('admin-role-list.edit.inline.title');

        $setDefaultsCallback = function(Container $container, RoleEntity $roleEntity) : void {
            $container->setDefaults(
                [
                    'name' => $roleEntity->name
                ]
            );
        };

        $onSubmitEdit = function(string $id, ArrayHash $values) : void {
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
                $this->grid->presenter->error('Role not found');
            }

            $roleEntity->name = $values->name;

            try {
                $this->em->persist($roleEntity);
                $this->em->flush();

                $this->grid->presenter->flashMessage(
                    $this->translator->translate('admin-role-list.edit.success', ['name' => $roleEntity->name]),
                    'success'
                );
                $this->grid->presenter->redrawControl('flashes');
                //$this->grid->redrawItem($id);
                ///$this->grid->reload();
                bdump('OK');
            } catch (DbalException $exception) {
                $this->grid->presenter->flashMessage($exception->getMessage(), 'danger');
                $this->grid->presenter->redrawControl('flashes');
                bdump('FAIL');
            }
        };

        $inlineEdit->onControlAdd[] = function(Container $container) : void {
            $container->addText('name')
                ->setRequired();
        };

        $inlineEdit->onSetDefaults[] = $setDefaultsCallback;
        $inlineEdit->onSubmit[] = $onSubmitEdit;
    }

}