<?php declare(strict_types=1);

namespace App\Core;

use App\UI\Admin\AdminBasePresenter;
use Nette\Application\Application;
use Nette\Application\IPresenter;
use Nette\Application\UI\Presenter;
use Nette\Security\AuthenticationException;

/**
 * class AutoLoginService
 *
 * Zajišťuje, že "Zůstat přihlášen" (viz LoginPresenter) skutečně funguje i mimo Admin sekci -
 * ta má vlastní, přísnější kontrolu v AdminBasePresenter::startup() (vyhazuje 401/403, když
 * cookie chybí/je neplatná - admin sekce MUSÍ být přihlášená). Tady je to naopak vždycky TICHÉ:
 * chybějící nebo neplatná cookie prostě znamená "nepřihlášený", stránka pokračuje normálně dál -
 * veřejná strana webu nesmí spadnout jen proto, že návštěvník nemá (platnou) auto-login cookie.
 *
 * Zapojeno v Bootstrap::runWebApplication() na Application::onPresenter - to se spustí pro
 * KAŽDÝ request, hned po vytvoření konkrétního presenteru a ještě před jeho run(), takže
 * `$presenter->getUser()->isLoggedIn()` je uvnitř toho presenteru už pravdivé, pokud se tu
 * přihlášení přes cookie povede.
 *
 * @package App\Core
 */
class AutoLoginService
{
    public function __construct(
        private readonly AutoLoginAuthenticator $autoLoginAuthenticator,
    ) {
    }

    public function tryLogin(Application $application, IPresenter $presenter) : void
    {
        if (!$presenter instanceof Presenter || $presenter instanceof AdminBasePresenter) {
            return;
        }

        $user = $presenter->getUser();

        if ($user->isLoggedIn()) {
            return;
        }

        $autoLoginCookie = $presenter->getHttpRequest()->getCookie(AutoLoginAuthenticator::COOKIE_NAME);

        if (!is_string($autoLoginCookie)) {
            return;
        }

        $user->setAuthenticator($this->autoLoginAuthenticator);

        try {
            $user->login($autoLoginCookie, '');
        } catch (AuthenticationException) {
            // neplatná/stará cookie - pokračuj jako nepřihlášený, nic nehlásit.
        }
    }
}
