<?php declare(strict_types=1);

namespace App\Database\Migrations;

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
        return 'create userActivation table';
    }

    public function up(Schema $schema) : void
    {
        $table = $schema->createTable('userActivation');

        $table->addColumn('id', Types::INTEGER)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('userId', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('activationKey', Types::STRING)
            ->setComment('Activation key')
            ->setLength(1024);

        $table->addColumn('validUntil', Types::DATETIME_IMMUTABLE)
            ->setComment('Key is valid until');

        $table->addColumn('createdAt', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updatedAt', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $table->setPrimaryKey(['id'])
            ->setComment('User activation keys')
            ->addUniqueIndex(['userId'], 'UK_UserActivation_UserId')
            ->addForeignKeyConstraint('user', ['userId'], ['id'], name: 'FK_UserActivation_UserId');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('userActivation');
    }

}

