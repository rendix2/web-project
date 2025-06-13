<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241105005240 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'create mail table';
    }

    public function up(Schema $schema) : void
    {
        $table = $schema->createTable('mail');

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('emailTo', Types::STRING)
            ->setLength(512)
            ->setComment('Email to');

        $table->addColumn('subject', Types::STRING)
            ->setLength(1024)
            ->setComment('Subject');

        $table->addColumn('body', Types::TEXT)
            ->setComment('Body');

        $table->addColumn('createdAt', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updatedAt', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $table->setPrimaryKey(['id'])
            ->setComment('Mail history')
            ->addIndex(['emailTo'], 'K_Mail_EmailTo')
            ->addForeignKeyConstraint($schema->getTable('userEmail'), ['emailTo'], ['email'], name: 'FK_Mail_EmailTo');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('mail');
    }

}
