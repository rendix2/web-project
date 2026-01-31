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
        $table = $schema->createTable('user_password');

        $table->addColumn('id', Types::INTEGER)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('user_id', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('password', Types::STRING)
            ->setComment('Password')
            ->setLength(1024);

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $table->setPrimaryKey(['id'])
            ->setComment('User password history')
            ->addIndex(['user_id'], 'K_UserPassword_UserId')
            ->addForeignKeyConstraint('users', ['user_id'], ['id'], name: 'FK_UserPassword_UserId');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('userPassword');
    }

}

