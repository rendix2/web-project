<?php declare(strict_types=1);

namespace App\Forms\Mapper;

use App\Model\Entity\RoleEntity;
use App\UI\Admin\Role\Create\CreateRoleValues;
use App\UI\Admin\Role\Edit\EditRoleValues;
use Nette\Forms\Container;

/**
 * The admin role forms (create + edit) <-> RoleEntity, written out field by field.
 */
class RoleFormMapper
{
    public function fill(Container $form, RoleEntity $role) : void
    {
        $form->setDefaults([
            'name' => $role->name,
        ]);
    }

    /**
     * Writes the submitted values onto the role (a new one when none is given).
     */
    public function apply(CreateRoleValues|EditRoleValues $values, ?RoleEntity $role = null) : RoleEntity
    {
        $role ??= new RoleEntity();
        $role->name = $values->name;

        return $role;
    }
}
