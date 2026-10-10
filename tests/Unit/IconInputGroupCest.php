<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Forms\IconBootstrapForm;
use App\Forms\Renderer\IconBootstrapRenderer;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Contributte\FormsBootstrap\BootstrapForm;
use Tests\Support\UnitTester;

/**
 * A control with the `icon` option is rendered as a Bootstrap 5 input group: the icon addon (`span.input-group-text`, a direct child
 * of `.input-group`, Bootstrap 5 has no addon wrapper) in front of the control itself.
 */
class IconInputGroupCest
{
    public function _before(UnitTester $I) : void
    {
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);
    }

    public function aTextInputGetsTheIconAddonInFrontOfIt(UnitTester $I) : void
    {
        $form = new IconBootstrapForm();
        $form->addText('name', 'Jméno')->setOption(IconBootstrapRenderer::ICON, 'fas fa-user');

        $html = (string) $form;

        $I->assertMatchesRegularExpression(
            '~<div class="input-group"><span class="input-group-text"><i class="fas fa-user fa-fw" aria-hidden="true"></i></span><input [^>]*name="name"~',
            $html
        );
    }

    public function textAreaAndSelectCanHaveTheIconToo(UnitTester $I) : void
    {
        $form = new IconBootstrapForm();
        $form->addTextArea('description', 'Popis')->setOption(IconBootstrapRenderer::ICON, 'fas fa-align-left');
        $form->addSelect('parent', 'Rodič', [1 => 'a'])->setOption(IconBootstrapRenderer::ICON, 'fas fa-sitemap');
        $form->addPassword('password', 'Heslo')->setOption(IconBootstrapRenderer::ICON, 'fas fa-lock');

        $html = (string) $form;

        $I->assertMatchesRegularExpression('~input-group-text"><i class="fas fa-align-left fa-fw"[^>]*></i></span><textarea ~', $html);
        $I->assertMatchesRegularExpression('~input-group-text"><i class="fas fa-sitemap fa-fw"[^>]*></i></span><select ~', $html);
        $I->assertMatchesRegularExpression('~input-group-text"><i class="fas fa-lock fa-fw"[^>]*></i></span><input [^>]*type="password"~', $html);
    }

    public function aControlWithoutTheIconIsNotWrapped(UnitTester $I) : void
    {
        $form = new IconBootstrapForm();
        $form->addText('name', 'Jméno');

        $I->assertStringNotContainsString('input-group', (string) $form);
    }

    public function noBootstrap4MarkupIsUsed(UnitTester $I) : void
    {
        $form = new IconBootstrapForm();
        $form->addText('name', 'Jméno')->setOption(IconBootstrapRenderer::ICON, 'fas fa-user');

        $html = (string) $form;

        $I->assertStringNotContainsString('input-group-' . 'prepend', $html);
        $I->assertStringNotContainsString('input-group-' . 'append', $html);
    }

    public function aSubmitWithTheIconIsAButtonWithTheIconInFrontOfTheCaption(UnitTester $I) : void
    {
        $form = new IconBootstrapForm();
        $form->addSubmit('save', 'Uložit')->setOption(IconBootstrapRenderer::ICON, 'fas fa-save');

        $html = (string) $form;

        $I->assertMatchesRegularExpression(
            '~<button type="submit" name="save" class="btn btn-primary"><i class="fas fa-save fa-fw" aria-hidden="true"></i> Uložit</button>~',
            $html
        );
        $I->assertStringNotContainsString('<input type="submit"', $html);
        // a button is not an input group
        $I->assertStringNotContainsString('input-group', $html);
    }

    public function aSubmitKeepsItsOwnClassesAndIsSubmittedByName(UnitTester $I) : void
    {
        $form = new IconBootstrapForm();
        $form->addSubmit('login', 'Přihlásit')
            ->setHtmlAttribute('class', 'btn-success btn-lg w-100')
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-sign-in-alt');

        $I->assertMatchesRegularExpression(
            '~<button type="submit" name="login" class="[^"]*btn-success[^"]*btn-lg[^"]*w-100[^"]*"><i class="fas fa-sign-in-alt fa-fw" aria-hidden="true"></i> Přihlásit</button>~',
            (string) $form
        );
    }

    public function aPlainButtonKeepsItsTypeWithTheIcon(UnitTester $I) : void
    {
        $form = new IconBootstrapForm();
        $form->addButton('refresh', 'Obnovit')->setOption(IconBootstrapRenderer::ICON, 'fas fa-sync');

        $I->assertMatchesRegularExpression(
            '~<button type="button" name="refresh" class="[^"]*"><i class="fas fa-sync fa-fw" aria-hidden="true"></i> Obnovit</button>~',
            (string) $form
        );
    }

    public function aSubmitWithoutTheIconIsNotChanged(UnitTester $I) : void
    {
        $form = new IconBootstrapForm();
        $form->addSubmit('save', 'Uložit');

        $I->assertStringContainsString('<input type="submit" name="save"', (string) $form);
    }

    public function theCaptionIsEscaped(UnitTester $I) : void
    {
        $form = new IconBootstrapForm();
        $form->addSubmit('save', '<b>x</b> & y')->setOption(IconBootstrapRenderer::ICON, 'fas fa-save');

        $I->assertStringContainsString('</i> &lt;b&gt;x&lt;/b&gt; &amp; y</button>', (string) $form);
    }

    public function theMarkupIsTheOneOfTheBootstrapDocumentation(UnitTester $I) : void
    {
        // `.has-validation` expects an `.invalid-feedback` as the LAST child of the group: without it the addon keeps its rounded corners
        // and the group looks torn apart. The feedback is a sibling outside of the group (and shown with `d-block`).
        $form = new IconBootstrapForm();
        $form->addText('name', 'Jméno')->setOption(IconBootstrapRenderer::ICON, 'fas fa-user');

        $html = (string) $form;

        $I->assertStringNotContainsString('has-validation', $html);
        $I->assertMatchesRegularExpression('~<div class="input-group"><span class="input-group-text">.*?</span><input class="form-control"|<div class="input-group"><span class="input-group-text">.*?</span><input [^>]*class="form-control"~', $html);
    }

    public function theErrorOfAControlInAnInputGroupIsVisible(UnitTester $I) : void
    {
        // `.is-invalid ~ .invalid-feedback` needs the message to be the control's sibling; in a group it is not, so it is shown explicitly
        $form = new IconBootstrapForm();
        $form->addText('name', 'Jméno')
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-user')
            ->addError('Zadejte prosím jméno.');

        $I->assertMatchesRegularExpression('~class="[^"]*invalid-feedback[^"]*d-block[^"]*"[^>]*>Zadejte prosím jméno\.~', (string) $form);
    }

    public function everyIconUsedInTheFormsExistsInTheBundledFontAwesome(UnitTester $I) : void
    {
        $css = dirname(__DIR__, 2) . '/www/assets/app.css';

        if (!is_file($css)) {
            $I->markTestSkipped('www/assets/app.css is not built (npm run build).');
        }

        preg_match_all('/\.(fa-[a-z0-9-]+)(?=[,{:])/', (string) file_get_contents($css), $defined);
        $known = array_flip($defined[1]);
        $unknown = [];
        $used = 0;

        foreach ($this->phpFiles() as $path => $content) {
            preg_match_all('/IconBootstrapRenderer::ICON,\s*\'([^\']+)\'/', $content, $matches);

            foreach ($matches[1] as $classList) {
                $used++;

                foreach (explode(' ', $classList) as $class) {
                    if (str_starts_with($class, 'fa-') && !isset($known[$class])) {
                        $unknown[] = $path . ' ' . $class;
                    }
                }
            }
        }

        $I->assertGreaterThan(15, $used, 'The scan did not find the icons of the forms.');
        $I->assertSame([], $unknown, "Unknown Font Awesome 5 icons:\n" . implode("\n", $unknown));
    }

    /**
     * @return iterable<string, string>
     */
    private function phpFiles() : iterable
    {
        $root = dirname(__DIR__, 2) . '/app';

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                yield substr($file->getPathname(), strlen($root) + 1) => (string) file_get_contents($file->getPathname());
            }
        }
    }

}
