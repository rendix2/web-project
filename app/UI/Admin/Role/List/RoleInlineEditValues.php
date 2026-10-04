<?php declare(strict_types=1);

namespace App\UI\Admin\Role\List;

/**
 * The datagrid adds its own controls (`_id`, submit buttons) to the same container -> dynamic properties.
 *
 * class RoleInlineEditValues
 *
 * @package App\UI\Admin\Role\List
 */
#[\AllowDynamicProperties]
class RoleInlineEditValues
{
    public string $name;
}