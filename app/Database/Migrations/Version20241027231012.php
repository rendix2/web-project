<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraintEditor;
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
        return 'create user_role table';
    }

    public function up(Schema $schema) : void
    {
        $table = $schema->createTable('public.user_role');

        $table->addColumn('user_id', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('role_id', Types::INTEGER)
            ->setComment('Role ID');

        $primaryKey = new PrimaryKeyConstraintEditor();
        $primaryKey->setIsClustered(false);
        $primaryKey->setColumnNames(
            new UnqualifiedName(Identifier::unquoted('user_id')),
            new UnqualifiedName(Identifier::unquoted('role_id'))
        );

        $table->addPrimaryKeyConstraint($primaryKey->create())
            ->setComment('User roles')
            ->addIndex(['user_id'], 'K__User_role__User_id')
            ->addIndex(['role_id'], 'K__User_role__Role_id')
            ->addForeignKeyConstraint('users', ['user_id'], ['id'], name: 'FK__User_role__User_id')
            ->addForeignKeyConstraint('role', ['role_id'], ['id'], name: 'FK__User_role__Role_id');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('public.user_role');
    }

}
