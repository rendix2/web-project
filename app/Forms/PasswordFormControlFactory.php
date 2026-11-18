<?php declare(strict_types=1);

namespace App\Forms;

interface PasswordFormControlFactory
{
    public function create(string $label) : PasswordFormControl;
}
