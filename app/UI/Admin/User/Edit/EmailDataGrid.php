<?php declare(strict_types=1);

namespace App\UI\Admin\User\Edit;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\UI\Grid\IdFilterCondition;
use App\UI\Grid\SecuredDatagrid;
use Contributte\Datagrid\Datagrid;
use Doctrine\ORM\QueryBuilder;
use Nette\Localization\Translator;
use Nette\Utils\Html;

class EmailDataGrid
{
    private SecuredDatagrid $grid;

    private UserEntity $userEntity;

    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly Translator             $translator,
    )
    {
        $this->grid = new SecuredDatagrid();
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
            ->setCondition(new IdFilterCondition('_email.id'))
            ->setPlaceholder('admin-user-list.id.search');

        $this->grid
            ->addColumnText('email', 'admin-user-edit.form.email.label')
            ->setRenderer(
                fn (UserEmailEntity $userEmailEntity) : string => $userEmailEntity->email
            );

        $this->grid
            ->addColumnDateTime('createdAt', 'admin-user-edit.emailGrid.createdAt')
            ->setRenderer(
                fn (UserEmailEntity $userEmailEntity) : string => $userEmailEntity->createdAt->format('d.m.Y')
            );

        $this->grid->setRowCallback(function (UserEmailEntity $item, Html $tr) : void {
            if ($item === $this->userEntity->emails->last()) {
                $tr->addClass('table-primary');
            }
        });

        return $this->grid;
    }
}
