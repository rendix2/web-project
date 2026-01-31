<?php declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Types\IpAddressType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250624214039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('user_login_attempt');

        $table->addColumn('id', Types::INTEGER)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('username', Types::STRING)
            ->setComment('Username')
            ->setLength(512);

        $table->addColumn('ip_address', IpAddressType::NAME)
            ->setComment('IP Address');

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->setPrimaryKey(['id'])
            ->setComment('User login attempts');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('userLoginAttempt');
    }

}
