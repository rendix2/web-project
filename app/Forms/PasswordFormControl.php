<?php declare(strict_types=1);

namespace App\Forms;

use App\Model\Entity\UserEntity;
use Jgxvx\Cilician\Service\Cilician;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use Contributte\FormsBootstrap\Inputs\TextInput;
use Nette\Security\Passwords;
use Nette\Utils\Html;

/**
 * class PasswordFormControl
 *
 * @package App\Forms
 */
class PasswordFormControl extends TextInput
{
    private const int MIN_LENGTH = 8;

    private bool $showPasswordEnabled = true;

    private ?UserEntity $historyUser = null;

    public function __construct(
        string                      $label,
        private readonly Translator $translator,
        private readonly Cilician   $cilician,
        private readonly Passwords  $passwords,
    )
    {
        parent::__construct($label);

        $this->setHtmlType('password');

        $this
            ->setRequired('admin-user-edit.form.password.required')
            ->addRule(Form::MinLength, (string) $this->translator->translate('admin-user-edit.form.password.ruleMinLength', ['minChars' => self::MIN_LENGTH]), self::MIN_LENGTH)
            ->addRule(
                function (TextInput $control): bool {
                    $password = $control->getValue();

                    if (!is_string($password) || $password === '') {
                        return true;
                    }

                    try {
                        $result = $this->cilician->checkPassword($password);

                        return !$result->isPwned();
                    } catch (\Exception $e) {
                        return true;
                    }

                },
                'admin-user-edit.form.password.pwnedError'
            )
            ->addRule(
                function (TextInput $control): bool {
                    $password = $control->getValue();

                    if ($this->historyUser === null || !is_string($password) || $password === '') {
                        return true;
                    }

                    foreach ($this->historyUser->passwords as $usedPassword) {
                        if ($this->passwords->verify($password, $usedPassword->password)) {
                            $presenter = $this->lookup(Presenter::class, false);

                            if ($presenter instanceof Presenter) {
                                $presenter->redrawControl();
                            }
                            return false;
                        }
                    }

                    return true;
                },
                'admin-user-edit.form.password.alreadyUsed'
            )
            ->addCondition(Form::MinLength, self::MIN_LENGTH)
                ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastNumber', '.*[0-9].*')
                ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastLowerChar', '.*[a-z].*')
                ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastUpperChar', '.*[A-Z].*')
            ->endCondition();
    }

    public function setShowPasswordEnabled(bool $showPasswordEnabled): void
    {
        $this->showPasswordEnabled = $showPasswordEnabled;
    }

    public function setHistoryUser(UserEntity $user): self
    {
        $this->historyUser = $user;
        return $this;
    }

    public function getControl(): Html
    {
        $control = parent::getControl();
        $control->addClass('form-control');

        $wrapper = Html::el('div')->addClass('input-group');

        $prepend = Html::el('span')->addClass('input-group-text');
        $prepend->addHtml(Html::el('i')->addClass('fa fa-lock'));
        $wrapper->addHtml($prepend);

        $wrapper->addHtml($control);

        if ($this->showPasswordEnabled) {
            $button = Html::el('button')
                ->addClass('btn btn-outline-secondary')
                ->type('button')
                ->addAttributes([
                    'data-toggle' => 'password',
                    'data-target' => $control->id
                ]);

            $icon = Html::el('i')->addClass('fa fa-eye');
            $button->addHtml($icon);

            $wrapper->addHtml($button);
        }

        return $wrapper;
    }

    public function getControlPart(): Html
    {
        return parent::getControl();
    }

    /**
     * Generates current control HTML.
     */
    public function generate(Html $control): Html
    {
        return $control;
    }
}

interface PasswordFormControlFactory
{
    public function create(string $label): PasswordFormControl;
}