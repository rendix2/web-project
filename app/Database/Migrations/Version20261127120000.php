<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The username and the e-mail are unique regardless of the case. The application compares them so (UserRepository), but its
 * check alone lets two parallel registrations of `Petr` and `petr` pass.
 *
 * Raw SQL on purpose: an expression index, DBAL cannot express it.
 */
final class Version20261127120000 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'users: unique indexes on lower(username) and lower(email)';
    }

    public function up(Schema $schema) : void
    {
        // an expression index: DBAL has no API for it
        $this->addSql('CREATE UNIQUE INDEX UK__Users__Lower_username ON users (lower(username))');
        // an expression index: DBAL has no API for it
        $this->addSql('CREATE UNIQUE INDEX UK__Users__Lower_email ON users (lower(email))');
    }

    public function down(Schema $schema) : void
    {
        // an expression index of up(): DBAL does not see it, so it cannot drop it
        $this->addSql('DROP INDEX UK__Users__Lower_username');
        // an expression index of up(): DBAL does not see it, so it cannot drop it
        $this->addSql('DROP INDEX UK__Users__Lower_email');
    }
}
