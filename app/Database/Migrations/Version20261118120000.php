<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * user_role: the primary key becomes (role_id, user_id) instead of (user_id, role_id). ORM builds the primary key of a join
 * table as (joinColumns, inverseJoinColumns) of the owning side (RoleEntity::$users), so every schema diff proposed to swap it
 * (tests/Integration/Schema/SchemaInSyncCest). Both columns keep their own index (every foreign key leads one).
 */
final class Version20261118120000 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'user_role: primary key (role_id, user_id)';
    }

    public function up(Schema $schema) : void
    {
        $table = $schema->getTable('user_role');
        $table->dropPrimaryKey();
        $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('role_id', 'user_id')->create());
    }

    public function down(Schema $schema) : void
    {
        $table = $schema->getTable('user_role');
        $table->dropPrimaryKey();
        $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('user_id', 'role_id')->create());
    }
}
