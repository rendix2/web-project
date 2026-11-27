<?php declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Attribute\DataProvider;
use Codeception\Example;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\FunctionalTester;

/**
 * Every page of the application is sent with the security headers (config/common.neon, `http:`). Whether the pages still work
 * under the Content-Security-Policy is measured in a browser (Acceptance/FrontendSmokeCest fails on a console error).
 */
class SecurityHeadersCest
{
    /**
     * @param Example<int, string> $example
     */
    #[DataProvider('pages')]
    public function thePageIsSentWithTheSecurityHeaders(FunctionalTester $I, Example $example) : void
    {
        $page = $example[0];
        assert(is_string($page));

        $response = $I->executeInGuzzle(static fn (Client $client) : ResponseInterface => $client->get($page));
        $I->assertInstanceOf(ResponseInterface::class, $response);
        $I->assertSame(200, $response->getStatusCode());

        $I->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        $I->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $I->assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
        $I->assertSame('max-age=31536000', $response->getHeaderLine('Strict-Transport-Security'));

        $policy = $response->getHeaderLine('Content-Security-Policy');

        foreach (["default-src 'self'", "script-src 'self' 'nonce-", "object-src 'none'", "base-uri 'self'", "form-action 'self'", "frame-ancestors 'none'"] as $directive) {
            $I->assertStringContainsString($directive, $policy);
        }

        $I->amOnPage($page);
        // an inline script would be blocked by the policy
        $I->dontSeeElement('script:not([src]):not([nonce])');
    }

    /**
     * @return list<list<string>>
     */
    protected function pages() : array
    {
        return [['/'], ['/web.user.login/'], ['/web.user.registration/']];
    }
}
