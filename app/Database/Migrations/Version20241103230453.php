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
final class Version20241103230453 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'create user_auto_login table';
    }

    public function up(Schema $schema) : void
    {
        $table = $schema->createTable('public.user_auto_login');

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('user_id', Types::BIGINT)
            ->setComment('User ID');

        // the cookie holds the token, the table only its SHA-256 hash (a leaked table logs nobody in)
        $table->addColumn('token_hash', Types::STRING)
            ->setComment('SHA-256 of the token saved in the cookie')
            ->setLength(64);

        $table->addColumn('valid_until', Types::DATETIME_IMMUTABLE)
            ->setComment('Token is valid until');

        $table->addColumn('ip_address', IpAddressType::NAME)
            ->setComment('IP address');

        $table->addColumn('user_agent', Types::STRING)
            ->setLength(512)
            ->setNotnull(false)
            ->setComment('User agent');

        // an ISO code (2 letters) or Local / Unknown
        $table->addColumn('country_code', Types::STRING)
            ->setLength(16)
            ->setNotnull(false)
            ->setComment('Country code');

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Updated at');

        $primaryKey = new PrimaryKeyConstraintEditor();
        $primaryKey->setIsClustered(false);
        $primaryKey->setColumnNames(new UnqualifiedName(Identifier::unquoted('id')));

        $table->addPrimaryKeyConstraint($primaryKey->create())
            ->setComment('User auto logins')
            ->addIndex(['user_id'], 'K__User_auto_login__User_id')
            ->addUniqueIndex(['token_hash'], 'UK__User_auto_login__Token_hash')
            ->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], name: 'FK__User_auto_login__User_id');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('public.user_auto_login');
    }

}
