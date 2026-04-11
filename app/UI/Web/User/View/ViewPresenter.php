<?php declare(strict_types=1);

namespace App\UI\Web\User\View;

use App\Model\Entity\UserEntity;
use App\Model\Repository\UserRepository;
use Nette\Application\UI\Presenter;
use App\Database\EntityManagerDecorator;

/**
 * class ViewPresenter
 *
 * @package App\UI\Web\User\View
 */
class ViewPresenter extends Presenter
{

    public function __construct(
        private readonly EntityManagerDecorator $em,
    )
    {
    }

    public function renderDefault(string $uuid) : void
    {
        /**
         * @var UserRepository $userRepository
         */
        $userRepository = $this->em
            ->getRepository(UserEntity::class);

        /**
         * @var UserEntity $userEntity
         */
        $userEntity = $userRepository->findOneByUuid($uuid);

        if (!$userEntity) {
            $this->error('User not found');
        }

        $this->template->userEntity = $userEntity;
    }

}
