<?php declare(strict_types=1);

namespace App\UI\Admin\User\List;

/**
 * class UserInlineEditValues
 *
 * @package App\UI\Admin\User\List
 */
final class UserInlineEditValues
{
    public string $fullName;
    public string $username;
    public string $email;
    public bool $isActive;
}