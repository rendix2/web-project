<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Accent-insensitive grid filters: the REGEXP() DQL function (App\Database\Functions\Regexp) uses unaccent().
 * The extension is "trusted" since PostgreSQL 13, so the database owner can create it.
 *
 * Raw SQL on purpose (the exception to the rule "migrations use the Schema API"): an extension is not a table, column or
 * index, DBAL has no API for it.
 */
final class Version20261003180000 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'enable the PostgreSQL unaccent extension (accent-insensitive grid filters)';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS unaccent');
    }

    public function down(Schema $schema) : void
    {
        // left installed on purpose: harmless, and other code may rely on it
    }
}
