<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241027231012 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'create userRoles table';
    }

    public function up(Schema $schema) : void
    {
        $table = $schema->createTable('userRole');

        $table->addColumn('userId', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('roleId', Types::INTEGER)
            ->setComment('Role ID');

        $table->setPrimaryKey(['userId', 'roleId'])
            ->setComment('User roles')
            ->addIndex(['userId'], 'K_UserRole_UserId')
            ->addIndex(['roleId'], 'K_UserRole_RoleId')
            ->addForeignKeyConstraint($schema->getTable('user'), ['userId'], ['id'], name: 'FK_UserRole_UserId')
            ->addForeignKeyConstraint($schema->getTable('role'), ['roleId'], ['id'], name: 'FK_UserRole_RoleId');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('userRole');
    }

}
