<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * OPcache is on and set the same way in alfred, web-project and family-tree (the same opcache.ini, in the dev and the production
 * image): the PHP running the tests uses every setting of the file, and both Dockerfiles copy it.
 */
class OpcacheSettingsCest
{
    private const string FILE = '.docker/web/config/opcache.ini';

    public function thePhpUsesTheSettingsOfTheFile(UnitTester $I) : void
    {
        $settings = parse_ini_file(dirname(__DIR__, 2) . '/' . self::FILE, false, INI_SCANNER_RAW);
        $I->assertIsArray($settings, self::FILE . ' cannot be read.');
        $I->assertNotSame([], $settings);

        $I->assertTrue(extension_loaded('Zend OPcache'), 'OPcache is not loaded.');

        foreach ($settings as $name => $expected) {
            $actual = ini_get((string) $name);
            $I->assertNotFalse($actual, 'Unknown setting ' . $name);
            $I->assertSame($this->normalize(is_scalar($expected) ? (string) $expected : ''), $this->normalize($actual), $name);
        }
    }

    public function bothImagesCopyTheFile(UnitTester $I) : void
    {
        foreach (['.docker/web/Dockerfile', '.docker/web/prod.Dockerfile'] as $dockerfile) {
            $source = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $dockerfile);
            $I->assertStringContainsString(dirname(self::FILE) . '/', $source, $dockerfile . ' does not copy ' . self::FILE);
            $I->assertDoesNotMatchRegularExpression('~echo\s+"opcache\.~', $source, $dockerfile . ' sets OPcache on its own - use ' . self::FILE);
        }
    }

    private function normalize(string $value) : string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            'on', 'true', 'yes' => '1',
            'off', 'false', 'no', '' => '0',
            default => $value,
        };
    }
}
