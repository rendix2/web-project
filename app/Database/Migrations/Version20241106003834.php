<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241106003834 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'create user forgotten password requests';
    }

    public function up(Schema $schema) : void
    {
        $table = $schema->createTable('userPasswordRequest');

        $table->addColumn('id', Types::INTEGER)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('userId', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('forgetKey', Types::STRING)
            ->setComment('Forget password key')
            ->setLength(1024);

        $table->addColumn('validUntil', Types::DATETIME_IMMUTABLE)
            ->setComment('Key is valid until');

        $table->addColumn('createdAt', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updatedAt', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $table->setPrimaryKey(['id'])
            ->setComment('User forgotten passwords requests')
            ->addUniqueIndex(['userId'], 'UK_UserPasswordRequest_UserId')
            ->addForeignKeyConstraint('user', ['userId'], ['id'], name: 'FK_UserPasswordRequest_UserId');

    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('userPasswordRequest');
    }

}


