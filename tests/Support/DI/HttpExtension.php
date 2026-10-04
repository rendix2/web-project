<?php

declare(strict_types=1);

namespace Tests\Support\DI;

use Nette\Http\IRequest;
use Tests\Support\Http\Request;

/**
 * Points the test container's httpRequest at Tests\Support\Http\Request instead of
 * Contributte\Codeception\Http\Request, which is missing isFrom().
 */
class HttpExtension extends \Contributte\Codeception\DI\HttpExtension
{
    public function beforeCompile(): void
    {
        parent::beforeCompile();

        $builder = $this->getContainerBuilder();
        $request = $builder->getByType(IRequest::class) ?? 'httpRequest';

        if ($builder->hasDefinition($request)) {
            $builder->getDefinition($request)->setFactory(Request::class);
        }
    }
}
