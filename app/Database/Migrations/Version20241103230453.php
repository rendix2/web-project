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

        $table->addColumn('id', Types::INTEGER)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('user_id', Types::BIGINT)
            ->setComment('User ID');

        $table->addColumn('token', Types::STRING)
            ->setComment('Token; saved in cookie')
            ->setLength(1024);

        $table->addColumn('ip_address', IpAddressType::NAME)
            ->setComment('IP address');

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
            ->addForeignKeyConstraint('users', ['user_id'], ['id'], name: 'FK__User_auto_login__User_id');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('public.user_auto_login');
    }

}
