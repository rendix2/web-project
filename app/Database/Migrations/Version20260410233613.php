<?php declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Types\IpAddressType;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraintEditor;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260410233613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('public.geo_ip_cache');

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('ip_address', IpAddressType::NAME)
            ->setComment('IP Address');

        $table->addColumn('country_code', Types::STRING)
            ->setLength(2)
            ->setComment('Country code');

        $table->addColumn('city', Types::STRING)
            ->setLength(255)
            ->setComment('City');

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $primaryKey = new PrimaryKeyConstraintEditor();
        $primaryKey->setIsClustered(false);
        $primaryKey->setColumnNames(new UnqualifiedName(Identifier::unquoted('id')));

        $table->addPrimaryKeyConstraint($primaryKey->create())
            ->setComment('Geo IP Cache');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('public.geo_ip_cache');
    }
}
