<?php declare(strict_types = 1);

namespace Database\Migrations;

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
		return 'create User table';
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

		$table->addColumn('createdAt', Types::DATETIME_IMMUTABLE)
			->setComment('createdAt');

		$table->addColumn('updatedAt', Types::DATETIME_IMMUTABLE)
			->setNotnull(false)
			->setComment('updatedAt');

		$table->setPrimaryKey(['id']);
		$table->setComment('User');
		$table->addUniqueIndex(['email'], 'K_User_Email');
		$table->addUniqueIndex(['username'], 'K_User_Username');
	}

	public function down(Schema $schema): void
	{
		$schema->dropTable('user');
	}

}
