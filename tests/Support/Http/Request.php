<?php

declare(strict_types=1);

namespace Tests\Support\Http;

use Nette\Http\FetchDest;
use Nette\Http\FetchSite;

/**
 * Adds isFrom() (used by Nette\Application\UI\AccessPolicy since nette/application 3.3),
 * which Contributte\Codeception\Http\Request does not implement. Codeception's internal
 * test requests never carry a real Sec-Fetch-Site header, so - like the pre-existing
 * isSameSite() stub on the parent class - this always reports same-origin/same-site.
 */
class Request extends \Contributte\Codeception\Http\Request
{
    public function isFrom(FetchSite|array $site, FetchDest|array|null $dest = null, ?bool $user = null): bool
    {
        return true;
    }
}
