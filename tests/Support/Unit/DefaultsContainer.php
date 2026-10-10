<?php declare(strict_types=1);

namespace Tests\Support\Unit;

use Nette\Forms\Container;

/**
 * A form container that only records what a mapper's `fill()` sets as defaults (`setDefaults()`): the mapper is tested without a form,
 * a presenter or a database.
 */
final class DefaultsContainer extends Container
{
    /** @var array<string, mixed> */
    public array $defaults;

    public function __construct()
    {
        $this->defaults = [];
    }

    public function setDefaults(array|object $values, bool $erase = false) : static
    {
        $this->defaults = (array) $values;

        return $this;
    }
}
