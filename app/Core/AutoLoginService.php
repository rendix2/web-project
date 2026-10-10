<?php declare(strict_types=1);

namespace App\Core;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEntity;
use App\UI\Admin\AdminBasePresenter;
use Nette\Application\Application;
use Nette\Application\IPresenter;
use Nette\Application\UI\Presenter;
use Nette\Security\AuthenticationException;

/**
 * class AutoLoginService
 *
 * Wired in Bootstrap::runWebApplication() on Application::onPresenter - that runs for EVERY request, right after the presenter is
 * created and before its run(), so `$presenter->getUser()->isLoggedIn()` is already true inside the presenter when the cookie worked.
 *
 * 1) A visitor that is logged in: a user that was deactivated (or deleted) since the session started is logged out now, his tokens
 *    are deleted. Deactivating a user therefore ends his sessions at the very next request.
 * 2) A visitor that is not logged in and has the "stay logged in" cookie: login() - the cookie is checked (AutoLoginAuthenticator) and
 *    REPLACED by a new one. The public side is always silent: a missing or invalid cookie means "not logged in", the page goes on.
 *    The admin has to be logged in, AdminBasePresenter::startup() calls login() itself and answers 401.
 *
 * @package App\Core
 */
class AutoLoginService
{
    public function __construct(
        private readonly AutoLoginAuthenticator $autoLoginAuthenticator,
        private readonly AutoLoginTokenService  $tokens,
        private readonly EntityManagerDecorator $em,
    ) {
    }

    public function tryLogin(Application $application, IPresenter $presenter) : void
    {
        if (!$presenter instanceof Presenter) {
            return;
        }

        if ($presenter->getUser()->isLoggedIn()) {
            $this->logoutInactiveUser($presenter);

            return;
        }

        if ($presenter instanceof AdminBasePresenter) {
            return;
        }

        $this->login($presenter);
    }

    /**
     * Logs the visitor in by the cookie. A cookie that does not work is deleted. Returns whether the visitor is logged in now.
     */
    public function login(Presenter $presenter) : bool
    {
        $user = $presenter->getUser();
        $cookie = $presenter->getHttpRequest()->getCookie(AutoLoginAuthenticator::COOKIE_NAME);

        if (!is_string($cookie)) {
            return false;
        }

        $user->setAuthenticator($this->autoLoginAuthenticator);

        try {
            $user->login($cookie, '');
        } catch (AuthenticationException) {
            $presenter->getHttpResponse()->deleteCookie(AutoLoginAuthenticator::COOKIE_NAME);

            return false;
        }

        $entity = $this->tokens->find($cookie);

        if ($entity !== null) {
            $presenter->getHttpResponse()->setCookie(AutoLoginAuthenticator::COOKIE_NAME, $this->tokens->rotate($entity), $this->lifetime());
        }

        return true;
    }

    private function logoutInactiveUser(Presenter $presenter) : void
    {
        $user = $presenter->getUser();
        $id = $user->getId();

        $userEntity = is_scalar($id) ? $this->em->getRepository(UserEntity::class)->findOneBy(['id' => $id]) : null;

        if ($userEntity !== null && $userEntity->isActive) {
            return;
        }

        if ($userEntity !== null) {
            $this->tokens->revokeAll($userEntity);
        }

        $user->logout(true);
        $presenter->getHttpResponse()->deleteCookie(AutoLoginAuthenticator::COOKIE_NAME);
    }

    private function lifetime() : string
    {
        return UserAutoLoginEntity::LIFETIME_DAYS . ' days';
    }

}
