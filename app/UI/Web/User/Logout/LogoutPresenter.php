<?php declare(strict_types=1);

namespace App\UI\Web\User\Logout;

use App\Core\AutoLoginAuthenticator;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEntity;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use App\Database\EntityManagerDecorator;

class LogoutPresenter extends Presenter
{
    public function __construct(
        private readonly Translator             $translator,
        private readonly EntityManagerDecorator $em,
    )
    {
        parent::__construct();
    }

    public function actionDefault() : void
    {
        // Odhlášení už odhlášeného není chyba (typicky dvojklik na odkaz, otevřená druhá záložka,
        // vrácení se v historii prohlížeče zpět) - stačí ho poslat na login, ne mu ukázat error page.
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect(':Web:User:Login:default');
        }

        if ($this->getHttpRequest()->getCookie(AutoLoginAuthenticator::COOKIE_NAME) !== null) {
            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->findOneBy(
                    [
                        'id' => $this->getUser()->getId(),
                    ]
                );

            if ($userEntity === null) {
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

            if ($userAutoLoginEntity !== null) {
                try {
                    $this->em->remove($userAutoLoginEntity);
                    $this->em->flush();
                } catch (DbalException $exception) {
                    // Smazání autologin záznamu selhalo, ale uživatel se odhlašuje ze session tak
                    // jako tak - nemá smysl mu to blokovat, jen to nahlásit do logu.
                    bdump($exception);
                }
            }
        }

        // Zruší i cookie v prohlížeči, ne jen záznam v DB - jinak by ji prohlížeč posílal navždy
        // (neškodně - AutoLoginAuthenticator ji po smazání entity výše stejně odmítne - ale zbytečně).
        $this->getHttpResponse()->deleteCookie(AutoLoginAuthenticator::COOKIE_NAME);

        $this->getUser()->logout(true);
        $this->flashMessage(
            $this->translator->translate('web-user-logout.success'),
            'success'
        );
        $this->redirect(':Web:User:Login:default');
    }

}
