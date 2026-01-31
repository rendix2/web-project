<?php declare(strict_types=1);

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
        $table = $schema->createTable('user_role');

        $table->addColumn('user_id', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('role_id', Types::INTEGER)
            ->setComment('Role ID');

        $table->setPrimaryKey(['user_id', 'role_id'])
            ->setComment('User roles')
            ->addIndex(['user_id'], 'K__UserRole__User_id')
            ->addIndex(['role_id'], 'K__UserRole__Role_id')
            ->addForeignKeyConstraint('users', ['user_id'], ['id'], name: 'FK__UserRole__User_id')
            ->addForeignKeyConstraint('role', ['role_id'], ['id'], name: 'FK__UserRole__Role_id');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('userRole');
    }

}
