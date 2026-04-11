<?php

namespace App\UI\Admin\User\Edit;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use Contributte\Datagrid\Datagrid;
use Doctrine\ORM\QueryBuilder;
use Nette\Localization\Translator;
use Nette\Utils\Html;

/**
 * class PasswordHistoryGrid
 *
 * @package App\UI\Admin\User\Edit
 */
class PasswordHistoryGrid
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

    public function setUserEntity(UserEntity $userEntity) : PasswordHistoryGrid
    {
        $this->userEntity = $userEntity;

        return $this;
    }

    public function create() : Datagrid
    {
        return $this->createColumns();
    }

    private function createDataSource() : QueryBuilder
    {
        return $this->em
            ->getRepository(UserPasswordEntity::class)
            ->createQueryBuilder('_password')

            ->where('_password.user = :user')
            ->setParameter('user', $this->userEntity);
    }

    private function createColumns() : Datagrid
    {
        $rows = $this->createDataSource();

        $this->grid->setDataSource($rows);
        $this->grid->setDefaultPerPage(10);
        $this->grid->setTranslator($this->translator);
        $this->grid->setColumnsHideable();
        $this->grid->setDefaultSort('createdAt');

        $this->grid
            ->addColumnNumber('id', 'admin-user-edit.passwordHistoryGrid.id')
            ->setDefaultHide(true)
            ->setSortable(true)
            ->setFilterText()
            ->setPlaceholder('admin-user-list.id.search');

        $this->grid
            ->addColumnDateTime('createdAt', 'admin-user-edit.passwordHistoryGrid.createdAt')
            ->setRenderer(
                function(UserPasswordEntity $userPasswordEntity) : string {
                    return $userPasswordEntity->createdAt->format('d.m.Y');
                }
            );

        $this->grid->setRowCallback(function(UserPasswordEntity $item, Html $tr) {
            if ($item === $this->userEntity->passwords->last()) {
                $tr->addClass('table-primary');
            }
        });

        return $this->grid;
    }
}