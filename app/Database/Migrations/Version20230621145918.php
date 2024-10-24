<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230621145918 extends AbstractMigration
{

	public function getDescription(): string
	{
		return 'create user table';
	}

	public function up(Schema $schema): void
	{
		$table = $schema->createTable('user');

		$table->addColumn('id', Types::BIGINT)
			->setAutoincrement(true)
			->setComment('ID');

		$table->addColumn('name', Types::STRING)
			->setComment('Name')
			->setLength(512);

		$table->addColumn('surname', Types::STRING)
			->setComment('Surname')
			->setLength(512);

		$table->addColumn('username', Types::STRING)
			->setComment('Username')
			->setLength(512);

		$table->addColumn('email', Types::STRING)
			->setComment('Email')
            ->setLength(1024);

		$table->addColumn('password', Types::STRING)
			->setComment('Password')
            ->setLength(1024);

		$table->addColumn('isActive', Types::BOOLEAN)
			->setComment('Is active?');

        $table->addColumn('lastLoginAt', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Last login at');

        $table->addColumn('lastLoginCount', Types::INTEGER)
            ->setComment('Last login count');

		$table->addColumn('createdAt', Types::DATETIME_IMMUTABLE)
			->setComment('Created at');

		$table->addColumn('updatedAt', Types::DATETIME_IMMUTABLE)
			->setNotnull(false)
			->setComment('Updated at');

		$table->setPrimaryKey(['id']);
		$table->setComment('Users');
		$table->addUniqueIndex(['email'], 'UK_User_Email');
		$table->addUniqueIndex(['username'], 'UK_User_Username');
	}

	public function down(Schema $schema): void
	{
		$schema->dropTable('user');
	}

}
