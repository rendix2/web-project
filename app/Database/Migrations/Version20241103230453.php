<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241103230453 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'create userAutoLogin table';
    }

    public function up(Schema $schema) : void
    {
        $table = $schema->createTable('userAutoLogin');

        $table->addColumn('id', Types::INTEGER)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('userId', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('token', Types::STRING)
            ->setComment('Token; saved in cookie')
            ->setLength(1024);

        $table->addColumn('ipAddress', Types::BINARY)
            ->setComment('IP address')
            ->setLength(16);

        $table->addColumn('createdAt', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updatedAt', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $table->setPrimaryKey(['id'])
            ->setComment('User auto logins')
            ->addIndex(['userId'], 'K_UserAutoLogin_UserId')
            ->addForeignKeyConstraint($schema->getTable('user'), ['userId'], ['id'], name: 'FK_UserAutoLogin_UserId');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('userAutoLogin');
    }

}
