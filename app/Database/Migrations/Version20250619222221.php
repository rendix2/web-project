<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250619222221 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('session');

        $table->addColumn('id', Types::STRING)
            ->setComment('ID')
            ->setLength(128)
            ->setNotnull(false);

        $table->addColumn('data', Types::TEXT)
            ->setComment('Data')
            ->setNotnull(true);

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setComment('Created At');

        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated At');

        $table->setPrimaryKey(['id'])
            ->setComment('Sessions');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('session');
    }

}
