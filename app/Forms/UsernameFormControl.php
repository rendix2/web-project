<?php declare(strict_types=1);

namespace App\Forms;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserEntity;
use Contributte\FormsBootstrap\Inputs\TextInput;
use Nette\Forms\Controls\BaseControl;
use Nette\Localization\Translator;
use Nette\Utils\Html;

class UsernameFormControl extends TextInput
{
    private string|int|null $excludeUserId = null;

    public function __construct(
        string                                  $label,
        private readonly Translator             $translator,
        private readonly EntityManagerDecorator $em
    ) {
        parent::__construct($label);

        $this
            ->setRequired('admin-user-edit.form.username.required')
            ->setMaxLength(512)
            ->addRule(
                function (BaseControl $control): bool
                {
                    $username = (string) $control->getValue();

                    if ($username === '') {
                        return true;
                    }

                    if ($this->checkUsernameExists($username)) {
                        $message = $this->translator
                            ->translate('admin-user-edit.form.username.exists', ['username' => $username]);

                        $control->addError($message);

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

    private function checkUsernameExists(string $username): bool
    {
        $qb = $this->em
            ->getRepository(UserEntity::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')

            ->where('u.username = :username')
            ->setParameter('username', $username);

        if ($this->excludeUserId !== null) {
            $qb
                ->andWhere('u.id != :excludeUserId')
                ->setParameter('excludeUserId', $this->excludeUserId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function getControl(): Html
    {
        $control = parent::getControl();
        $control->addClass('form-control');

        $wrapper = Html::el('div')->addClass('input-group');
        $addon = Html::el('span')->addClass('input-group-text');
        $icon = Html::el('i')->addClass('fa fa-user');

        $addon->addHtml($icon);
        $wrapper->addHtml($addon);
        $wrapper->addHtml($control);

        return $wrapper;
    }
}

interface UsernameFormControlFactory
{
    public function create(string $label): UsernameFormControl;
}