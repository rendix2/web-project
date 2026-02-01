<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraintEditor;
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
        $table = $schema->createTable('public.mail');

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('email_to', Types::STRING)
            ->setLength(512)
            ->setComment('Email to');

        $table->addColumn('subject', Types::STRING)
            ->setLength(1024)
            ->setComment('Subject');

        $table->addColumn('body', Types::TEXT)
            ->setComment('Body');

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $primaryKey = new PrimaryKeyConstraintEditor();
        $primaryKey->setIsClustered(false);
        $primaryKey->setColumnNames(new UnqualifiedName(Identifier::unquoted('id')));

        $table->addPrimaryKeyConstraint($primaryKey->create())
            ->setComment('Mail history')
            ->addIndex(['email_to'], 'K__Mail__Email_to')
            ->addForeignKeyConstraint('user_email', ['email_to'], ['email'], name: 'FK__Mail__Email_to');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('public.mail');
    }

}
