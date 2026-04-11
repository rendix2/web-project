<?php declare(strict_types=1);

namespace App\Forms;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Inputs\TextInput;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Nette\Localization\Translator;
use Nette\Utils\Html;

/**
 * class EmailFormControl
 */
class EmailFormControl extends TextInput
{
    private string|int|null $excludeUserId = null;

    public function __construct(
        string                                  $label,
        private readonly Translator             $translator,
        private readonly EntityManagerDecorator $em
    )
    {
        parent::__construct($label);

        $this
            ->setHtmlType('email')
            ->setNullable(BootstrapForm::$allwaysUseNullable)
            ->setRequired('admin-user-edit.form.email.required')
            ->addRule(Form::Email, 'admin-user-edit.form.email.invalidFormat')
            ->setMaxLength(512)

            ->addRule(
                function (BaseControl $control): bool {
                    $email = (string) $control->getValue();

                    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                        return true;
                    }

                    if ($this->checkEmailExists($email)) {
                        $control->addError($this->translator->translate('admin-user-edit.form.email.exists', ['email' => $email]));

                        return true;
                    }

                    return true;
                }
            );
    }

    public function setExcludeUserId(string|int|null $userId): self
    {
        $this->excludeUserId = $userId;

        return $this;
    }

    private function checkEmailExists(string $email): bool
    {
        $qbUser = $this
            ->em
            ->getRepository(UserEntity::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')

            ->where('u.email = :email')
            ->setParameter('email', $email);

        if ($this->excludeUserId !== null) {
            $qbUser->andWhere('u.id != :excludeUserId')
                ->setParameter('excludeUserId', $this->excludeUserId);
        }

        if ((int) $qbUser->getQuery()->getSingleScalarResult() > 0) {
            return true;
        }

        $qbHistory = $this
            ->em
            ->getRepository(UserEmailEntity::class)
            ->createQueryBuilder('ue')
            ->select('COUNT(ue.id)')

            ->where('ue.email = :email')
            ->setParameter('email', $email);

        if ($this->excludeUserId !== null) {
            $qbHistory
                ->andWhere('ue.user != :excludeUserId')
                ->setParameter('excludeUserId', $this->excludeUserId);
        }

        return (int) $qbHistory->getQuery()->getSingleScalarResult() > 0;
    }

    public function getControl(): Html
    {
        $control = parent::getControl();
        $control->addClass('form-control');

        $wrapper = Html::el('div')->addClass('input-group');

        $addon = Html::el('span')->addClass('input-group-text');
        $icon = Html::el('i')->addClass('fa fa-at');

        $addon->addHtml($icon);

        $wrapper->addHtml($addon);
        $wrapper->addHtml($control);

        return $wrapper;
    }
}

interface EmailFormControlFactory
{
    public function create(string $label): EmailFormControl;
}