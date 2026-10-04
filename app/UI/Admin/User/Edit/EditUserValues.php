<?php declare(strict_types=1);

namespace App\UI\Admin\User\Edit;

/**
 * class EditUserValues
 *
 * @package App\UI\Admin\User\Edit
 */
final class EditUserValues
{
    public string $name;
    public string $surname;
    public string $username;
    public string $email;
    public string $password;
    public bool $isActive;
}
