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
        $table = $schema->createTable('userEmail');

        $table->addColumn('id', Types::INTEGER)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('userId', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('email', Types::STRING)
            ->setComment('Email')
            ->setLength(512);

        $table->addColumn('createdAt', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updatedAt', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $table->setComment('User email history')
            ->setPrimaryKey(['id'])
            ->addIndex(['userId'], 'K_UserEmail_UserId')
            ->addForeignKeyConstraint('user', ['userId'], ['id'], name: 'FK_UserEmail_UserId')
            ->addUniqueIndex(['email'], 'UK_UserEmail_Email');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('userEmail');
    }

}
