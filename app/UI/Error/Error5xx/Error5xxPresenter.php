<?php declare(strict_types = 1);

namespace App\UI\Error\Error5xx;

use Nette\Application\IPresenter;
use Nette\Application\Attributes\Requires;
use Nette\Application\Request;
use Nette\Application\Response;
use Tracy\ILogger;

use Nette\Application\Responses;
use Nette\Http;

/**
 * Handles uncaught exceptions and errors, and logs them.
 */
#[Requires(forward: true)]
final class ErrorPresenter implements IPresenter
{

	public function __construct(
		private ILogger $logger,
	) {
	}

	public function run(Request $request): Response
	{
		// Log the exception
		$exception = $request->getParameter('exception');
		$this->logger->log($exception, ILogger::EXCEPTION);

		// Display a generic error message to the user
		return new Responses\CallbackResponse(function (Http\IRequest $httpRequest, Http\IResponse $httpResponse): void {
			if (preg_match('#^text/html(?:;|$)#', (string) $httpResponse->getHeader('Content-Type'))) {
				require __DIR__ . '/500.phtml';
			}
		});
	}

}
