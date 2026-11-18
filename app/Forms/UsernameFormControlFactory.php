<?php declare(strict_types=1);

namespace App\Forms;

interface UsernameFormControlFactory
{
    public function create(string $label) : UsernameFormControl;
}
