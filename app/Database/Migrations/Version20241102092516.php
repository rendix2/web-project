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
final class Version20241102092516 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'create user_activation table';
    }

    public function up(Schema $schema) : void
    {
        $table = $schema->createTable('public.user_activation');

        $table->addColumn('id', Types::INTEGER)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('user_id', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('activation_key', Types::STRING)
            ->setComment('Activation key')
            ->setLength(1024);

        $table->addColumn('valid_until', Types::DATETIME_IMMUTABLE)
            ->setComment('Key is valid until');

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $primaryKey = new PrimaryKeyConstraintEditor();
        $primaryKey->setIsClustered(false);
        $primaryKey->setColumnNames(new UnqualifiedName(Identifier::unquoted('id')));

        $table->addPrimaryKeyConstraint($primaryKey->create())
            ->setComment('User activation keys')
            ->addUniqueIndex(['user_id'], 'UK__user_activation__user_id')
            ->addForeignKeyConstraint('users', ['user_id'], ['id'], name: 'FK__user_activation__user_id');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('public.user_activation');
    }

}

