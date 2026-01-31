<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241023000710 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'create userPassword table';
    }

    public function up(Schema $schema) : void
    {
        $table = $schema->createTable('userPassword');

        $table->addColumn('id', Types::INTEGER)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('userId', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('password', Types::STRING)
            ->setComment('Password')
            ->setLength(1024);

        $table->addColumn('createdAt', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updatedAt', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $table->setPrimaryKey(['id'])
            ->setComment('User password history')
            ->addIndex(['userId'], 'K_UserPassword_UserId')
            ->addForeignKeyConstraint('users', ['userId'], ['id'], name: 'FK_UserPassword_UserId');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('userPassword');
    }

}

