<?php declare(strict_types=1);

namespace App\UI\Web\User\Logout;

use App\Core\AutoLoginAuthenticator;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEntity;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Presenter;
use Nette\Http\IResponse;
use Nette\Localization\Translator;
use Nettrine\ORM\EntityManagerDecorator;

class LogoutPresenter extends Presenter
{
    public function __construct(
        private readonly Translator             $translator,
        private readonly EntityManagerDecorator $em,
    )
    {
    }

    public function actionDefault() : void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->error('User not logged in', IResponse::S400_BadRequest);
        }

        if ($this->getHttpRequest()->getCookie(AutoLoginAuthenticator::COOKIE_NAME) !== null) {
            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->findOneBy(
                    [
                        'id' => $this->getUser()->getId(),
                    ]
                );

            if (!$userEntity) {
                $this->error('user not found');
            }

            $userAutoLoginEntity = $this->em
                ->getRepository(UserAutoLoginEntity::class)
                ->findOneBy(
                    [
                        'user' => $userEntity,
                        'token' => $this->getHttpRequest()->getCookie(AutoLoginAuthenticator::COOKIE_NAME),
                    ]
                );

            if ($userAutoLoginEntity) {
                try {
                    $this->em->remove($userAutoLoginEntity);
                    $this->em->flush();
                } catch (DbalException $exception) {
                    $this->flashMessage($exception->getMessage(), 'danger');
                    $this->redrawControl('flashes');
                }
            }
        }

        $this->getUser()->logout(true);
        $this->flashMessage(
            $this->translator->translate('web-user-logout.success'),
            'success'
        );
        $this->redirect(':Web:User:Login:default');
    }

}
