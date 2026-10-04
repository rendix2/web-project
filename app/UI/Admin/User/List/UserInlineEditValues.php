<?php declare(strict_types=1);

namespace App\UI\Admin\User\List;

/**
 * The datagrid adds its own controls (`_id`, submit buttons) to the same container -> dynamic properties.
 *
 * class UserInlineEditValues
 *
 * @package App\UI\Admin\User\List
 */
#[\AllowDynamicProperties]
final class UserInlineEditValues
{
    public string $fullName;
    public string $username;
    public string $email;
    public int|string|bool|null $isActive;
}