<?php declare(strict_types=1);

namespace App\Forms;

use App\Forms\Renderer\IconBootstrapRenderer;
use Contributte\FormsBootstrap\BootstrapForm;

/**
 * BootstrapForm whose controls can show an icon in front of them (a Bootstrap 5 input group), see IconBootstrapRenderer::ICON.
 */
class IconBootstrapForm extends BootstrapForm
{
    public function __construct()
    {
        parent::__construct();

        $this->setRenderer(new IconBootstrapRenderer());
    }

}
