<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241023000710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'create user_password table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('user_password');

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('userId', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('password', Types::STRING)
            ->setComment('Password')
            ->setLength(1024);

        $table->addColumn('createdAt', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updatedAt', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $table->setPrimaryKey(['id']);
        $table->setComment('User passwords');

        $table->addIndex(['userId'], 'K_User_password_UserId');

        $table->addForeignKeyConstraint($schema->getTable('user'), ['userId'], ['id'], name: 'FK_User_password_UserId');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('user_password');
    }

}

