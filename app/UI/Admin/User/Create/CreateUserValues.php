<?php declare(strict_types=1);

namespace App\UI\Admin\User\Create;

/**
 * class CreateUserValues
 *
 * @package App\UI\Admin\User\Create
 */
final class CreateUserValues
{
    public string $name;
    public string $surname;
    public string $username;
    public string $email;
    public string $password;
    public bool $isActive;
}