<?php declare(strict_types=1);

namespace App\Forms\Mapper;

use App\Model\Entity\UserEntity;
use App\UI\Admin\User\Create\CreateUserValues;
use App\UI\Admin\User\Edit\EditUserValues;
use Nette\Forms\Container;

/**
 * The admin user forms (create + edit) <-> UserEntity, written out field by field.
 *
 * Only the plain fields are mapped here. The password (hash, history, auto-logins) and the e-mail history are not fields but
 * domain rules: they stay in `UserFacade` / the create presenter, which call `apply()` for the rest.
 */
class UserFormMapper
{
    public function fill(Container $form, UserEntity $user) : void
    {
        $form->setDefaults([
            'name' => $user->name,
            'surname' => $user->surname,
            'username' => $user->username,
            'email' => $user->email,
            'isActive' => $user->isActive,
        ]);
    }

    /**
     * Writes the submitted plain fields onto the user (a new one when none is given).
     */
    public function apply(CreateUserValues|EditUserValues $values, ?UserEntity $user = null) : UserEntity
    {
        $user ??= new UserEntity();

        $user->name = $values->name;
        $user->surname = $values->surname;
        $user->username = $values->username;
        $user->email = $values->email;
        $user->isActive = $values->isActive;

        return $user;
    }
}
