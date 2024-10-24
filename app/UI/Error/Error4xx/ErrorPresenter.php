<?php declare(strict_types=1);

namespace App\UI\Error\Error4xx;

use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;
use Nette\Application\Request;
use Nette\Application\UI\Presenter;

#[Requires(methods: '*', forward: true)]
final class ErrorPresenter extends Presenter
{

    public function startup() : void
    {
        parent::startup();

        if (!$this->getRequest()->isMethod(Request::FORWARD)) {
            $this->error();
        }
    }

    public function renderDefault(BadRequestException $exception) : void
    {
        // renders the appropriate error template based on the HTTP status code
        $code = $exception->getCode();
        $file = is_file($file = __DIR__ . "/$code.latte")
            ? $file
            : __DIR__ . '/4xx.latte';
        $this->template->httpCode = $code;
        $this->template->setFile($file);
    }

}
