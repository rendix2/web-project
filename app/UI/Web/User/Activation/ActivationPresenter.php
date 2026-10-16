<?php declare(strict_types=1);

namespace App\UI\Web\User\Activation;

use stdClass;
use App\Model\Entity\UserActivationEntity;
use App\Model\Entity\UserEntity;
use DateTimeImmutable;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use App\Database\EntityManagerDecorator;

class ActivationPresenter extends Presenter
{
    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly Translator             $translator,
    )
    {
        parent::__construct();
    }

    public function actionDefault(string $userId, string $key) : void
    {
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $userId,
                ]
            );

        if ($userEntity === null) {
            $this->error('User not found');
        }

        $userActivationEntity = $this->em
            ->getRepository(UserActivationEntity::class)
            ->findOneBy(
                [
                    'activationKey' => $key,
                    'user' => $userEntity,
                ]
            );

        if ($userActivationEntity === null) {
            $this->error('Invalid activation key');
        }

        if ((new DateTimeImmutable()) > $userActivationEntity->validUntil) {
            $this->error('Invalid activation key');
        }

        $userActivationEntity->user->isActive = true;
        $userActivationEntity->user->updatedAt = new DateTimeImmutable();

        $this->em->remove($userActivationEntity);
        $this->em->flush();

        $flash = new stdClass();
        $flash->place = 'general';
        $flash->type = 'success';
        $flash->message = $this->translator->translate('web-user-activation.success');

        $this->flashMessage($flash);
        $this->redrawControl('flashes');

        $this->redirect(':Web:User:Login:default');
    }

}