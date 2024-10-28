<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241027230245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'create roles table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('role');

        $table->addColumn('id', Types::INTEGER)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('name', Types::STRING)
            ->setComment('Name')
            ->setLength(512);

        $table->addColumn('description', Types::TEXT)
            ->setComment('Name');

        $table->addColumn('createdAt', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updatedAt', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $table->setPrimaryKey(['id']);
        $table->setComment('Roles');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('role');
    }
}
