<?php

namespace App\UI\Admin\User\Edit;

use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use Contributte\Datagrid\Datagrid;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\QueryBuilder;
use Nette\Localization\Translator;
use Nette\Security\User;
use Nettrine\ORM\EntityManagerDecorator;

class EmailsDataGridFactory
{

    private Datagrid $grid;

    private User $user;

    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly Translator             $translator,
    )
    {
        $this->grid = new Datagrid();
    }

    public function setUser(User $user) : EmailsDataGridFactory
    {
        $this->user = $user;

        return $this;
    }

    public function create() : Datagrid
    {
        $this->createColumns();

        return $this->grid;
    }

    private function createDataSource() : QueryBuilder
    {
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $this->user->getId(),
                ]
            );

        return $this->em
            ->getRepository(UserEmailEntity::class)
            ->createQueryBuilder('_email')

            ->where('_email.user = :user')
            ->setParameter('user', $userEntity);
    }

    private function createColumns() : void
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
    }



}