<?php declare(strict_types=1);

namespace App\UI\Error\Error4xx;

use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;

/**
 * Handles 4xx HTTP error responses.
 */
#[Requires(methods: '*', forward: true)]
final class ErrorPresenter extends Presenter
{
    public function renderDefault(BadRequestException $exception) : void
    {
        // renders the appropriate error template based on the HTTP status code
        $code = $exception->getCode();
        $file = is_file($file = __DIR__ . "/$code.latte")
            ? $file
            : __DIR__ . '/4xx.latte';

        if ($this->template instanceof DefaultTemplate) {
            $this->template->setFile($file);
        }

        $this->template->httpCode = $code;
    }

}
