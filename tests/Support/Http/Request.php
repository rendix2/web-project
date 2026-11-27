<?php declare(strict_types=1);

namespace Tests\Support\Http;

use Nette\Http\FetchDest;
use Nette\Http\FetchSite;
use Nette\Http\RequestFactory;

/**
 * Adds isFrom() (used by Nette\Application\UI\AccessPolicy since nette/application 3.3),
 * which Contributte\Codeception\Http\Request does not implement. Codeception's internal
 * test requests never carry a real Sec-Fetch-Site header, so - like the pre-existing
 * isSameSite() stub on the parent class - this always reports same-origin/same-site.
 */
class Request extends \Contributte\Codeception\Http\Request
{
    private bool $remoteAddressHidden;

    public function __construct(RequestFactory $factory)
    {
        parent::__construct($factory);

        $this->remoteAddressHidden = false;
    }

    /**
     * From now on the requests of this container come without a remote address (the NetteConnector always sets 127.0.0.1).
     */
    public function hideRemoteAddress() : void
    {
        $this->remoteAddressHidden = true;
    }

    public function getRemoteAddress() : ?string
    {
        return $this->remoteAddressHidden ? null : parent::getRemoteAddress();
    }

    /**
     * @param FetchSite|array<mixed> $site
     * @param FetchDest|array<mixed>|null $dest
     */
    public function isFrom(FetchSite|array $site, FetchDest|array|null $dest = null, ?bool $user = null) : bool
    {
        return true;
    }
}
