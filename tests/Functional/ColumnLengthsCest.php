<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserLoginAttemptEntity;
use Tests\Support\FunctionalTester;

/**
 * `country_code` of a login attempt / auto login holds an ISO code (2 letters) or the words GeoIpService / LoginPresenter use
 * for "no code" (`Local`, `Unknown`): 16 characters, in the entity and in the database alike (the entity said 2, the database had no limit).
 */
class ColumnLengthsCest
{
    public function entitiesAndTablesAgreeOnTheLengthOfTheColumns(FunctionalTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $columns = [
            [UserLoginAttemptEntity::class, 'user_login_attempt', 'countryCode', 'country_code', 16],
            [UserLoginAttemptEntity::class, 'user_login_attempt', 'userAgent', 'user_agent', 512],
            [UserAutoLoginEntity::class, 'user_auto_login', 'countryCode', 'country_code', 16],
            [UserAutoLoginEntity::class, 'user_auto_login', 'userAgent', 'user_agent', 512],
        ];

        foreach ($columns as [$class, $table, $field, $column, $length]) {
            $mapped = $em->getClassMetadata($class)->getFieldMapping($field)->length;
            $stored = $em->getConnection()->fetchOne(
                'SELECT character_maximum_length FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
                [$table, $column]
            );

            $I->assertSame($length, $mapped, $class . '::$' . $field . ' (mapping)');
            $I->assertSame($length, (int) $stored, $table . '.' . $column . ' (database)');
        }
    }

    public function theWordsWithoutAnIsoCodeFit(FunctionalTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $attempt = new UserLoginAttemptEntity();
        $attempt->username = 'column-length-test';
        $attempt->ipAddress = '10.66.66.66';
        $attempt->userAgent = str_repeat('a', 512);
        $attempt->countryCode = 'Unknown';

        $em->persist($attempt);
        $em->flush();

        $I->assertGreaterThan(0, $attempt->id);

        $em->remove($attempt);
        $em->flush();
    }

}
