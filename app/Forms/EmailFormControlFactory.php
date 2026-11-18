<?php declare(strict_types=1);

namespace App\Forms;

interface EmailFormControlFactory
{
    public function create(string $label) : EmailFormControl;
}
