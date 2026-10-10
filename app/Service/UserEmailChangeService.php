<?php declare(strict_types=1);

namespace App\Service;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\MailEntity;
use App\Model\Entity\UserEmailChangeRequestEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use Contributte\Mailing\IMailBuilderFactory;
use DateTimeImmutable;
use Exception;
use Nette\Localization\Translator;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Throwable;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Changing the e-mail of an account in two steps. request(): nothing is changed, the NEW address gets a link (the token is stored
 * only as its hash, valid 24 hours, one pending request per user) and the OLD address gets a warning. confirm(): the link of the new
 * address saves it, the old address is told. So the password reset of an account can never be redirected to a mailbox its owner does
 * not control, even by somebody who holds his session or his password.
 */
class UserEmailChangeService
{
    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly IMailBuilderFactory    $mailBuilderFactory,
        private readonly Translator             $translator,
    ) {
    }

    /**
     * @throws Throwable the mail could not be sent (the request is removed again)
     */
    public function request(UserEntity $user, string $newEmail) : void
    {
        $newEmail = $this->normalize($newEmail);

        $this->em->createQuery('DELETE FROM ' . UserEmailChangeRequestEntity::class . ' r WHERE r.user = :user')
            ->setParameter('user', $user)
            ->execute();

        $token = bin2hex(random_bytes(32));

        $request = new UserEmailChangeRequestEntity();
        $request->user = $user;
        $request->newEmail = $newEmail;
        $request->tokenHash = hash('sha256', $token);

        $this->em->persist($request);
        $this->em->flush();

        try {
            $this->send($newEmail, $user, 'confirm', ['userEntity' => $user, 'token' => $token, 'newEmail' => $newEmail]);
            $this->send($user->email, $user, 'requested', ['userEntity' => $user, 'newEmail' => $newEmail]);
        } catch (Throwable $exception) {
            $this->em->remove($request);
            $this->em->flush();

            throw $exception;
        }
    }

    /**
     * @throws EmailChangeException
     */
    public function confirm(string $userId, string $token) : UserEntity
    {
        $request = ctype_digit($userId) && preg_match('~^[0-9a-f]{64}$~', $token) === 1
            ? $this->em->getRepository(UserEmailChangeRequestEntity::class)->findOneBy(['user' => $userId, 'tokenHash' => hash('sha256', $token)])
            : null;

        if ($request === null) {
            throw new EmailChangeException('invalid');
        }

        if ($request->validUntil < new DateTimeImmutable()) {
            $this->em->remove($request);
            $this->em->flush();

            throw new EmailChangeException('invalid');
        }

        $user = $request->user;
        $newEmail = $request->newEmail;

        if ($this->isTakenByAnother($user, $newEmail)) {
            $this->em->remove($request);
            $this->em->flush();

            throw new EmailChangeException('taken');
        }

        $oldEmail = $user->email;

        $user->changeEmail($newEmail);
        $user->updatedAt = new DateTimeImmutable();

        $this->em->remove($request);
        $this->em->flush();

        try {
            $this->send($oldEmail, $user, 'changed', ['userEntity' => $user, 'newEmail' => $newEmail]);
        } catch (Throwable $exception) {
            // the e-mail IS changed, only the notice failed
            Debugger::log($exception, ILogger::EXCEPTION);
        }

        return $user;
    }

    private function isTakenByAnother(UserEntity $user, string $email) : bool
    {
        $count = (int) $this->em->createQuery('SELECT COUNT(u.id) FROM ' . UserEntity::class . ' u WHERE LOWER(u.email) = :email AND u.id != :id')
            ->setParameter('email', $email)
            ->setParameter('id', $user->id)
            ->getSingleScalarResult();

        $history = (int) $this->em->createQuery('SELECT COUNT(e.id) FROM ' . UserEmailEntity::class . ' e WHERE LOWER(e.email) = :email AND e.user != :user')
            ->setParameter('email', $email)
            ->setParameter('user', $user)
            ->getSingleScalarResult();

        return $count + $history > 0;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function send(string $to, UserEntity $user, string $template, array $parameters) : void
    {
        if (!$this->translator instanceof LocaleAwareInterface) {
            throw new Exception('Unknown translator without locale');
        }

        $subject = (string) $this->translator->translate('web-user-changeEmail.mail.' . $template . '.subject');

        $mail = $this->mailBuilderFactory->create();
        $mail->addTo($to, $user->name . ' ' . $user->surname);
        $mail->setSubject($subject);
        $mail->setTemplateFile(__DIR__ . '/../UI/Web/User/ChangeEmail/Mailing/' . $template . '.' . $this->translator->getLocale() . '.latte');
        $mail->setParameters($parameters);
        $mail->send();

        $body = $mail->getMessage()->getHtmlBody();
        $token = $parameters['token'] ?? null;

        $mailEntity = new MailEntity();
        $mailEntity->emailTo = $to;
        $mailEntity->subject = $subject;
        // the token of the link is the secret of the request: the log of the mails (a table) must not keep a working one
        $mailEntity->body = is_string($token) ? str_replace($token, '[token]', $body) : $body;

        $this->em->persist($mailEntity);
        $this->em->flush();
    }

    private function normalize(string $email) : string
    {
        return mb_strtolower(trim($email));
    }

}
