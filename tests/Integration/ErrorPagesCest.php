<?php declare(strict_types=1);

namespace Tests\Integration;

use App\UI\Error\Error4xx\Error4xxPresenter;
use App\UI\Error\Error5xx\Error5xxPresenter;
use Nette\Application\BadRequestException;
use Nette\Application\IPresenterFactory;
use Nette\Application\Request;
use Nette\Application\Responses\TextResponse;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Neon\Neon;
use Tests\Support\IntegrationTester;

/**
 * The error pages of production (debug mode off: the application forwards an exception to the error presenter).
 *
 * Regression (2026-11-18): `errorPresenter: 4xx: Error:Error4xx, 5xx: Error:Error5xx` maps (`App\UI\*\**Presenter`) to
 * `App\UI\Error\Error4xx\Error4xxPresenter`, but the classes were both named `ErrorPresenter`: the forward failed with "Cannot load
 * presenter", so in production every 404 / 403 was Tracy's general 500 page. Debug mode (dev, the tests) never forwards, nothing
 * noticed it.
 *
 * In the test process the application cannot forward by itself (the test runner has sent output, the HTTP response counts as
 * sent), so the presenter gets the forward here.
 */
class ErrorPagesCest
{
    /**
     * The names come from config/common.neon itself: in debug mode the ApplicationExtension does not hand them to the application.
     */
    public function theConfiguredErrorPresentersExist(IntegrationTester $I) : void
    {
        $config = Neon::decodeFile(dirname(__DIR__, 2) . '/config/common.neon');
        $errorPresenter = is_array($config) && is_array($config['application'] ?? null) ? $config['application']['errorPresenter'] ?? null : null;
        $I->assertSame(['4xx' => 'Error:Error4xx', '5xx' => 'Error:Error5xx'], $errorPresenter);

        $factory = $I->grabService(IPresenterFactory::class);
        $I->assertInstanceOf(Error4xxPresenter::class, $factory->createPresenter('Error:Error4xx'));
        $I->assertInstanceOf(Error5xxPresenter::class, $factory->createPresenter('Error:Error5xx'));
    }

    public function theErrorPageOfTheCodeIsShown(IntegrationTester $I) : void
    {
        $page404 = $this->render($I, 404);
        $page418 = $this->render($I, 418);

        $I->assertStringContainsString('404', $page404);
        $I->assertStringContainsString('418', $page418);
        $I->assertNotSame($page404, $page418);
    }

    private function render(IntegrationTester $I, int $code) : string
    {
        $presenter = $I->grabService(IPresenterFactory::class)->createPresenter('Error:Error4xx');
        $response = $presenter->run(new Request('Error:Error4xx', Request::Forward, ['exception' => new BadRequestException('Refused', $code)]));

        $I->assertInstanceOf(TextResponse::class, $response);
        $template = $response->getSource();
        $I->assertInstanceOf(Template::class, $template);

        return $template->renderToString();
    }
}
