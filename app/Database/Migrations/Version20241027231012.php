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
    public function getDescription(): string
    {
        return 'create userRoles table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('userRole');

        $table->addColumn('userId', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('roleId', Types::INTEGER)
            ->setComment('Role ID');

        $table->addIndex(['userId'], 'K_UserRole_UserId');
        $table->addIndex(['roleId'], 'K_UserRole_RoleId');

        $table->addForeignKeyConstraint($schema->getTable('user'), ['userId'], ['id'], name: 'FK_UserRole_UserId');
        $table->addForeignKeyConstraint($schema->getTable('role'), ['roleId'], ['id'], name: 'FK_UserRole_RoleId');

        $table->setPrimaryKey(['userId', 'roleId']);
        $table->setComment('User Roles');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('userRole');
    }

}
