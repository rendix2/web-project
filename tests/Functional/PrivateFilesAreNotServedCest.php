<?php declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Attribute\DataProvider;
use Codeception\Example;
use Tests\Support\FunctionalTester;

/**
 * Nothing outside www/ can be downloaded over HTTP: the configuration (database and mail passwords), the source, the logs
 * (they hold e-mails and stack traces with arguments), the temp files, vendor, git. The document root is www/
 * (DOCUMENTROOT in docker-compose.yml, .docker/web/prod.Dockerfile): with a wrong one every file of the project is a URL.
 * Lesson of the alfred leak of 2026-11-11 (config/*.neon could be downloaded from the root of the web). Same test in alfred
 * and family-tree.
 */
class PrivateFilesAreNotServedCest
{
    /**
     * @param Example<string, string> $example
     */
    #[DataProvider('privatePaths')]
    public function aPrivateFileIsNotServed(FunctionalTester $I, Example $example) : void
    {
        $path = $example['path'];
        assert(is_string($path));

        $I->amOnPage($path);

        // the application answers (404 in production; in the dev environment Tracy shows the BadRequestException #404 as a
        // 500 page): never 2xx/3xx
        $I->seeResponseCodeIsBetween(400, 599);

        // and the file itself never comes back: its beginning (where the file exists in this checkout) is not in the answer
        $file = dirname(__DIR__, 2) . '/' . ltrim((string) preg_replace('~^/(?:www/\.\./|%2e%2e/)~', '/', $path), '/');

        if (is_file($file)) {
            $content = trim((string) file_get_contents($file));
            $beginning = substr($content, 0, 120);

            if ($beginning !== '') {
                // str_contains, not assertStringNotContainsString: a failure must not print the file (passwords) into the test output
                $I->assertFalse(str_contains($I->grabPageSource(), $beginning), $path . ' returned the file.');
            }
        }
    }

    /**
     * Files that exist in every checkout (and the usual names of what is git-ignored), each at the root and one level up.
     *
     * @return list<array{path: string}>
     */
    protected function privatePaths() : array
    {
        $paths = [
            '/config/common.neon',
            '/config/services.neon',
            '/config/local.neon',
            '/config/env/dev.neon',
            '/config/env/prod.neon',
            '/config/ext/nettrine.neon',
            '/app/Bootstrap.php',
            '/app/Core/RouterFactory.php',
            '/log/exception.log',
            '/log/web/exception.log',
            '/temp/web/mails/',
            '/vendor/autoload.php',
            '/composer.json',
            '/composer.lock',
            '/package.json',
            '/docker-compose.yml',
            '/Makefile',
            '/.git/config',
            '/.env',
            '/tests/_bootstrap.php',
            '/www/../config/common.neon',
            '/%2e%2e/config/common.neon',
        ];

        return array_map(static fn (string $path) : array => ['path' => $path], $paths);
    }
}
