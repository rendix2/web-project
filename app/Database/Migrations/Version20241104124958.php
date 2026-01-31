<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241104124958 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'create userEmail history table';
    }

    public function up(Schema $schema) : void
    {
        $table = $schema->createTable('user_email');

        $table->addColumn('id', Types::INTEGER)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('user_id', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('email', Types::STRING)
            ->setComment('Email')
            ->setLength(512);

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $table->setComment('User email history')
            ->setPrimaryKey(['id'])
            ->addIndex(['user_id'], 'K__UserEmail__User_id')
            ->addForeignKeyConstraint('users', ['user_id'], ['id'], name: 'FK__UserEmail__User_id')
            ->addUniqueIndex(['email'], 'UK_UserEmail_Email');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('userEmail');
    }

}
