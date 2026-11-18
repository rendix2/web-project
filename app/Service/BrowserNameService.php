<?php declare(strict_types=1);

namespace App\Service;

use donatj\UserAgent\UserAgentParser;
use Nette\Http\IRequest;

/**
 * The name of the browser of the current request (for the records of logins and of "stay logged in").
 * A request without a User-Agent header (curl, a bot, a test) has none: null, never an exception
 * (UserAgentParser::parse() without an argument throws "parse_user_agent requires a user agent").
 */
class BrowserNameService
{
    public function __construct(
        private readonly IRequest $request,
    )
    {
    }

    public function current() : ?string
    {
        $userAgent = $this->request->getHeader('User-Agent');

        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return (new UserAgentParser())->parse($userAgent)->browser();
    }
}
