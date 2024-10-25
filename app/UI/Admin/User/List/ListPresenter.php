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
use Nette\Security\User;
use Nette\Utils\ArrayHash;
use Nettrine\ORM\EntityManagerDecorator;

class ListPresenter extends Presenter
{
    public function __construct(
        private readonly DataGridFactory $dataGridFactory
    )
    {
    }

    public function actionDefault()
    {
    }

    public function createComponentGrid() : Datagrid
    {
        return $this->dataGridFactory->create();
    }

}
