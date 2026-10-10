<?php declare(strict_types=1);

namespace App\Core;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEntity;
use DateTimeImmutable;

/**
 * The "stay logged in" tokens. The cookie holds a random token, the database only its SHA-256 hash (UNIQUE): somebody who reads the
 * table cannot log in with what he finds there. A token is valid for UserAutoLoginEntity::LIFETIME_DAYS and is replaced by a new one
 * every time it is used (rotate()), so a stolen cookie works once at most - and the owner's next visit then fails visibly.
 */
class AutoLoginTokenService
{
    public function __construct(
        private readonly EntityManagerDecorator $em,
    ) {
    }

    /**
     * A new token for the user; the caller sets the cookie to the returned value.
     */
    public function issue(UserEntity $user, string $ipAddress, ?string $countryCode, ?string $userAgent) : string
    {
        $token = $this->generate();

        $entity = new UserAutoLoginEntity();
        $entity->user = $user;
        $entity->tokenHash = $this->hash($token);
        $entity->ipAddress = $ipAddress;
        $entity->countryCode = $countryCode;
        $entity->userAgent = $userAgent;

        $this->em->persist($entity);
        $this->em->flush();

        return $token;
    }

    /**
     * The valid (not expired) record of the token of the cookie; an expired one is deleted.
     */
    public function find(string $token) : ?UserAutoLoginEntity
    {
        $entity = $this->em->getRepository(UserAutoLoginEntity::class)->findOneBy(['tokenHash' => $this->hash($token)]);

        if ($entity === null) {
            return null;
        }

        if ($entity->validUntil < new DateTimeImmutable()) {
            $this->em->remove($entity);
            $this->em->flush();

            return null;
        }

        return $entity;
    }

    /**
     * Replaces the token of the record by a new one and extends its validity; returns the new token (the cookie).
     */
    public function rotate(UserAutoLoginEntity $entity) : string
    {
        $token = $this->generate();

        $entity->tokenHash = $this->hash($token);
        $entity->validUntil = new DateTimeImmutable('+' . UserAutoLoginEntity::LIFETIME_DAYS . ' days');
        $entity->updatedAt = new DateTimeImmutable();

        $this->em->flush();

        return $token;
    }

    public function revoke(string $token) : void
    {
        $this->em->createQuery('DELETE FROM ' . UserAutoLoginEntity::class . ' a WHERE a.tokenHash = :hash')
            ->setParameter('hash', $this->hash($token))
            ->execute();
    }

    public function revokeAll(UserEntity $user) : void
    {
        $this->em->createQuery('DELETE FROM ' . UserAutoLoginEntity::class . ' a WHERE a.user = :user')
            ->setParameter('user', $user)
            ->execute();
        // the identity map still holds the removed rows
        $user->autoLogins->clear();
    }

    public function hash(string $token) : string
    {
        return hash('sha256', $token);
    }

    private function generate() : string
    {
        return bin2hex(random_bytes(32));
    }

}
