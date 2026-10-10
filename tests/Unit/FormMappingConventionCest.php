<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * The form <-> entity mapping lives in `app/Forms/Mapper/XFormMapper`: every mapper has a Unit test, and the admin presenters do not
 * set the defaults of an entity form / write form values onto an entity themselves.
 */
class FormMappingConventionCest
{
    public function everyMapperHasAUnitTest(UnitTester $I) : void
    {
        $missing = [];

        foreach (glob(__DIR__ . '/../../app/Forms/Mapper/*FormMapper.php') ?: [] as $file) {
            $name = basename($file, '.php');

            if (!is_file(__DIR__ . '/Forms/Mapper/' . $name . 'Cest.php')) {
                $missing[] = $name;
            }
        }

        $I->assertSame([], $missing, 'A mapper needs tests/Unit/Forms/Mapper/<Name>Cest.php: ' . implode(', ', $missing));
    }

    public function adminPresentersMapThroughTheMappers(UnitTester $I) : void
    {
        $violations = [];

        foreach (glob(__DIR__ . '/../../app/UI/Admin/{User,Role}/{Edit,Create}/*Presenter.php', GLOB_BRACE) ?: [] as $file) {
            $source = (string) file_get_contents($file);

            // `$this['editForm']->setDefaults(` = the entity's fields copied into the form by hand
            if (preg_match('~\$this\[\'\w+Form\'\]->setDefaults\(~', $source) === 1) {
                $violations[] = str_replace(__DIR__ . '/../../', '', $file);
            }
        }

        $I->assertSame([], $violations, 'Use the XFormMapper::fill() in: ' . implode(', ', $violations));
    }
}
