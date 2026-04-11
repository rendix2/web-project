<?php

namespace App\UI\Admin\User\Edit;

use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use Contributte\Datagrid\Datagrid;
use Doctrine\ORM\QueryBuilder;
use Nette\Localization\Translator;
use App\Database\EntityManagerDecorator;
use Nette\Utils\Html;

class EmailDataGrid
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

    public function setUserEntity(UserEntity $userEntity) : EmailDataGrid
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
            ->getRepository(UserEmailEntity::class)
            ->createQueryBuilder('_email')

            ->where('_email.user = :user')
            ->setParameter('user', $this->userEntity);
    }

    private function createColumns() : Datagrid
    {
        $this->grid->setDataSource($this->createDataSource());
        $this->grid->setDefaultPerPage(10);
        $this->grid->setTranslator($this->translator);
        $this->grid->setColumnsHideable();
        $this->grid->setDefaultSort('id');

        $this->grid
            ->addColumnNumber('id', 'admin-user-list.id.name')
            ->setDefaultHide(true)
            ->setSortable(true)
            ->setFilterText()
            ->setPlaceholder('admin-user-list.id.search');

        $this->grid
            ->addColumnText('email', 'admin-user-edit.form.email.label')
            ->setRenderer(
                function(UserEmailEntity $userEmailEntity) : string {
                    return $userEmailEntity->email;
                }
            );

        $this->grid
            ->addColumnDateTime('createdAt', 'admin-user-edit.emailGrid.createdAt')
            ->setRenderer(
                function(UserEmailEntity $userEmailEntity) : string {
                    return $userEmailEntity->createdAt->format('d.m.Y');
                }
            );

        $this->grid->setRowCallback(function(UserEmailEntity $item, Html $tr) {
            if ($item === $this->userEntity->emails->last()) {
                $tr->addClass('table-primary');
            }
        });

        return $this->grid;
    }
}
