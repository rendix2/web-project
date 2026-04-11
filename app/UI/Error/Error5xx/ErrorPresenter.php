<?php declare(strict_types=1);

namespace App\UI\Error\Error5xx;

use Nette\Application\Attributes\Requires;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses\CallbackResponse;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Tracy\ILogger;

/**
 * Handles uncaught exceptions and errors, and logs them.
 */
#[Requires(forward: true)]
final class ErrorPresenter implements IPresenter
{

    public function __construct(
        private readonly ILogger $logger,
    )
    {
    }

    public function run(Request $request) : Response
    {
        // Log the exception
        $exception = $request->getParameter('exception');
        $this->logger->log($exception, ILogger::EXCEPTION);

        // Display a generic error message to the user
        return new CallbackResponse(function(IRequest $httpRequest, IResponse $httpResponse) : void {
            $contentType = (string) $httpResponse->getHeader('Content-Type');

            if (preg_match('#^text/html(?:;|$)#', $contentType) === 1) {
                require __DIR__ . '/500.phtml';
            }
        });
    }

}
