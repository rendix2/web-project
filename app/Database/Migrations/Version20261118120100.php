<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * role.description had the comment "Name" (a copy of the column above it); the entity says "Description".
 */
final class Version20261118120100 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'role.description: comment "Description"';
    }

    public function up(Schema $schema) : void
    {
        $schema->getTable('role')
            ->getColumn('description')
            ->setComment('Description');
    }

    public function down(Schema $schema) : void
    {
        $schema->getTable('role')
            ->getColumn('description')
            ->setComment('Name');
    }
}
