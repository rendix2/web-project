<?php declare(strict_types=1);

namespace App\UI\Web\User\Activation;

use App\Model\Entity\UserActivationEntity;
use App\Model\Entity\UserEntity;
use DateTimeImmutable;
use Nette\Application\UI\Presenter;
use App\Database\EntityManagerDecorator;

class ActivationPresenter extends Presenter
{
    public function __construct(
        private readonly EntityManagerDecorator $em,
    )
    {
    }

    public function actionDefault(string $key, string $userId) : void
    {
        $user = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $userId,
                ]
            );

        if (!$user) {
            $this->error('user not found');
        }

        $userActivationEntity = $this->em
            ->getRepository(UserActivationEntity::class)
            ->findOneBy(
                [
                    'activationKey' => $key,
                    'user' => $user,
                ]
            );

        if (!$userActivationEntity) {
            $this->error('User not found');
        }

        if ((new DateTimeImmutable()) > $userActivationEntity->validUntil) {
            $this->error('invalid act key');
        }

        $userActivationEntity->user->isActive = true;
        $userActivationEntity->user->updatedAt = new DateTimeImmutable();

        $this->em->persist($userActivationEntity);
        $this->em->remove($userActivationEntity);
        $this->em->flush();

        $this->flashMessage('success', 'success');
        $this->redrawControl('flashes');

        $this->redirect(':Web:User:Login:default');
    }

}