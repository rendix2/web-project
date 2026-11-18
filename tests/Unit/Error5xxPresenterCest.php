<?php declare(strict_types=1);

namespace Tests\Unit;

use App\UI\Error\Error5xx\Error5xxPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\CallbackResponse;
use Nette\Http\Request as HttpRequest;
use Nette\Http\Response as HttpResponse;
use Nette\Http\UrlScript;
use RuntimeException;
use Tests\Support\UnitTester;
use Tracy\ILogger;

/**
 * The 5xx error page logs the exception (as an EXCEPTION, Tracy then mails it in production) and answers with a callback that
 * renders the static 500 page only for an HTML response.
 */
class Error5xxPresenterCest
{
    public function theExceptionIsLoggedAndTheResponseIsTheStaticPage(UnitTester $I) : void
    {
        $logger = new class() implements ILogger {
            /** @var list<array{mixed, string}> */
            public array $logged;

            public function __construct()
            {
                $this->logged = [];
            }

            public function log(mixed $value, string $level = self::INFO) : void
            {
                $this->logged[] = [$value, $level];
            }
        };

        $exception = new RuntimeException('Broken');
        $response = (new Error5xxPresenter($logger))->run(new Request('Error:Error5xx', 'GET', ['exception' => $exception]));

        $I->assertInstanceOf(CallbackResponse::class, $response);
        $I->assertSame([[$exception, ILogger::EXCEPTION]], $logger->logged);

        // the CLI sends no Content-Type header: nothing is rendered (the callback runs without an error)
        ob_start();
        $response->send(new HttpRequest(new UrlScript('http://example.com/')), new HttpResponse());
        $I->assertSame('', ob_get_clean());
    }
}
