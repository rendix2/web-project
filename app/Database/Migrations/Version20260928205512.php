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
 * Rate limiting žádostí o obnovu hesla - viz
 * App\UI\Web\User\ForgetPassword\Request\UserPasswordRequestAttemptCheckService.
 */
final class Version20260928205512 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'create user password request attempt table (rate limiting for forgotten password requests)';
    }

    public function up(Schema $schema) : void
    {
        $table = $schema->createTable('public.user_password_request_attempt');

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setComment('ID');

        $table->addColumn('email', Types::STRING)
            ->setComment('Email')
            ->setLength(512);

        $table->addColumn('ip_address', IpAddressType::NAME)
            ->setComment('IP Address');

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setComment('Created at');

        $table->addIndex(['email', 'created_at'], 'K__User_password_request_attempt__Email_created_at');
        $table->addIndex(['ip_address', 'created_at'], 'K__User_password_request_attempt__Ip_address_created_at');

        $primaryKey = new PrimaryKeyConstraintEditor();
        $primaryKey->setIsClustered(false);
        $primaryKey->setColumnNames(new UnqualifiedName(Identifier::unquoted('id')));

        $table->addPrimaryKeyConstraint($primaryKey->create())
            ->setComment('User password request attempts (rate limiting)');
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('public.user_password_request_attempt');
    }
}
