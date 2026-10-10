<?php declare(strict_types=1);

namespace App\Forms\Renderer;

use Contributte\FormsBootstrap\BootstrapRenderer;
use Contributte\FormsBootstrap\Enums\RendererConfig as Cnf;
use Contributte\FormsBootstrap\Enums\RendererOptions;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Controls\Button;
use Nette\Utils\Html;

/**
 * A control with the `icon` option (`$control->setOption(IconBootstrapRenderer::ICON, 'fas fa-user')`) shows the icon:
 * - an input (text, number, date, password, text area, select) is rendered as a Bootstrap 5 input group, the icon addon in front of it,
 * - a button (submit or plain) is rendered as `<button>` with the icon in front of the caption.
 */
class IconBootstrapRenderer extends BootstrapRenderer
{
    public const ICON = 'icon';

    public function renderControl(BaseControl $control) : string
    {
        $icon = $control->getOption(self::ICON);

        if ($control instanceof Button && is_string($icon)) {
            return $this->renderIconButton($control, $icon);
        }

        $controlHtml = parent::renderControl($control);

        if (!is_string($icon)) {
            return $controlHtml;
        }

        // Bootstrap 5: the addon is a direct child of .input-group (Bootstrap 4 wrapped it in an extra element)
        $addon = Html::el('span', ['class' => 'input-group-text'])
            ->addHtml(Html::el('i', ['class' => $icon . ' fa-fw', 'aria-hidden' => 'true']));

        return (string) Html::el('div', ['class' => 'input-group'])
            ->addHtml($addon)
            ->addHtml($controlHtml);
    }

    private function renderIconButton(Button $control, string $icon) : string
    {
        $element = $control->getControl();
        $control->setOption(RendererOptions::_RENDERED, true);

        $iconHtml = Html::el('i', ['class' => $icon . ' fa-fw', 'aria-hidden' => 'true']);

        if ($element->getName() === 'button') {
            $element->insert(0, $iconHtml);
            $element->insert(1, ' ');
        } else {
            // <input type="submit" value="caption"> cannot hold an icon: the same attributes on a <button>
            $caption = $element->getAttribute('value');
            $button = Html::el('button')->addAttributes($element->attrs);
            $button->setAttribute('value', null);
            $button->addHtml($iconHtml)->addText(' ' . (is_string($caption) ? $caption : ''));
            $element = $button;
        }

        return (string) $this->configElem(Cnf::INPUT, $element);
    }

    protected function renderFeedback(?BaseControl $control = null) : ?Html
    {
        $feedback = parent::renderFeedback($control);

        // `.is-invalid ~ .invalid-feedback` needs the message to be a sibling of the control, inside the input group it is not
        if ($feedback !== null && $control !== null && is_string($control->getOption(self::ICON))) {
            $feedback->addClass('d-block');
        }

        return $feedback;
    }

}
