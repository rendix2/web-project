<?php

namespace App\UI\Web\User\ForgetPassword\Request;

use App\Model\Entity\MailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordRequestEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Contributte\Mailing\IMailBuilderFactory;
use Doctrine\DBAL\Exception as DbalException;
use Exception;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use Nette\Utils\Random;
use App\Database\EntityManagerDecorator;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Throwable;

class RequestPresenter extends Presenter
{
    public function __construct(
        private readonly EntityManagerDecorator                 $em,
        private readonly Translator                             $translator,
        private readonly IMailBuilderFactory                    $mailBuilderFactory,
        private readonly UserPasswordRequestAttemptCheckService $attemptCheckService,
    )
    {
        parent::__construct();
    }

    public function renderDefault() : void
    {
    }

    public function createComponentRequestForm() : BootstrapForm
    {
        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);

        $form->addEmail('email', 'admin-user-edit.form.email.label')
            ->setRequired('admin-user-edit.form.email.required')
            ->setMaxLength(512);

        $form->addSubmit('request', 'web-user-forgetPassword-request.form.submit.label');

        $form->onSuccess[] = [$this, 'requestFormSuccess'];

        return $form;
    }

    /**
     * Odpověď (i časování, nakolik to jde) je ZÁMĚRNĚ stejná bez ohledu na to, jestli daný e-mail
     * v databázi existuje - jinak by šlo formulář zneužít k enumeraci registrovaných e-mailů
     * (zkoušet adresy a podle chybové/úspěšné odpovědi poznat, kdo má účet). Nikdy sem proto
     * nepřidávej `$this->error(...)`/jinou odlišitelnou odpověď pro "e-mail nenalezen".
     */
    public function requestFormSuccess(Form $form) : void
    {
        $values = $form->getValues(CreatePasswordRequestValues::class);
        $ip = $this->getHttpRequest()->getRemoteAddress() ?? '0.0.0.0';
        $succeeded = true;

        if (!$this->attemptCheckService->isIpBlocked($ip) && !$this->attemptCheckService->isEmailBlocked($values->email)) {
            $this->attemptCheckService->logAttempt($values->email, $ip);

            $userEntity = $this->em
                ->getRepository(UserEntity::class)
                ->findOneByEmail($values->email);

            if ($userEntity !== null) {
                $succeeded = $this->createAndSendPasswordRequest($userEntity);
            }
        }

        // Stejná hláška vždy - ať byl e-mail nalezen, neznámý, nebo je žadatel dočasně
        // zablokovaný kvůli příliš mnoha požadavkům (viz komentář výše) - ALE jen když
        // nepředchází skutečná provozní chyba (ta už svou vlastní hlášku ukázala a další
        // "úspěšná" hláška vedle ní by byla matoucí, ne bezpečnostně nutná - provozní chyba
        // sama o sobě nic o existenci účtu neprozrazuje).
        if ($succeeded) {
            $this->flashMessage($this->translator->translate('web-user-forgetPassword-request.form.submit.success'));
        }

        $this->redrawControl('flashes');
    }

    private function createAndSendPasswordRequest(UserEntity $userEntity) : bool
    {
        $oldRequests = $this->em
            ->getRepository(UserPasswordRequestEntity::class)
            ->findBy(
                [
                    'user' => $userEntity,
                ]
            );

        foreach ($oldRequests as $oldRequest) {
            $this->em->remove($oldRequest);
        }

        $this->em->flush();

        $userPasswordRequestEntity = new UserPasswordRequestEntity();
        $userPasswordRequestEntity->user = $userEntity;
        $userPasswordRequestEntity->forgetKey = Random::generate(256);

        $userEntity->addUserPasswordRequestEntity($userPasswordRequestEntity);

        try {
            $this->em->persist($userEntity);
            $this->em->flush();
        } catch (DbalException $exception) {
            // Nikdy neposílat e-mail s odkazem na žádost, která se ve skutečnosti neuložila -
            // odkaz by byl po kliknutí vždy "neplatný". Chyba samotná je jen provozní (neprozrazuje
            // nic o existenci účtu), takže se dá klidně přeložená a jasně ukázat.
            bdump($exception);
            $this->flashMessage($this->translator->translate('web-user-forgetPassword-request.form.submit.error'), 'danger');
            $this->redrawControl('flashes');

            return false;
        }

        $mail = $this->mailBuilderFactory->create();

        $mail->addTo($userEntity->email, $userEntity->name . ' ' . $userEntity->surname);
        $mail->setSubject($this->translator->translate('web-user-forgetPassword-request.subject'));

        if ($this->translator instanceof LocaleAwareInterface) {
            $mail->setTemplateFile(__DIR__ . '/Mailing/request.' . $this->translator->getLocale() . '.latte');
        } else {
            throw new Exception('Unknown translator without locale');
        }

        $mail->setParameters(
            [
                'userEntity' => $userEntity,
            ]
        );

        try {
            $mail->send();
        } catch (Throwable $exception) {
            bdump($exception);
            $this->flashMessage($this->translator->translate('web-user-forgetPassword-request.form.submit.error'), 'danger');
            $this->redrawControl('flashes');

            return false;
        }

        $mailEntity = new MailEntity();
        $mailEntity->emailTo = $userEntity->email;
        $mailEntity->body = $mail->getMessage()->getHtmlBody();
        $mailEntity->subject = $this->translator->translate('web-user-forgetPassword-request.subject');

        try {
            $this->em->persist($mailEntity);
            $this->em->flush();
        } catch (Throwable $exception) {
            // E-mail se v tuhle chvíli už odeslal - selhalo jen uložení do auditního logu mailů,
            // to uživateli do flash zprávy hlásit nemá smysl (nic z jeho pohledu nezhavarovalo).
            bdump($exception);
        }

        return true;
    }
}
