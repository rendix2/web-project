<?php declare(strict_types=1);

namespace App\Forms;

use Nette\Forms\Control;
use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserEntity;
use Contributte\FormsBootstrap\Inputs\TextInput;
use Nette\Forms\Controls\BaseControl;
use Nette\Localization\Translator;
use App\Forms\Renderer\IconBootstrapRenderer;

class UsernameFormControl extends TextInput
{
    private string|int|null $excludeUserId = null;

    public function __construct(
        string                                  $label,
        private readonly Translator             $translator,
        private readonly EntityManagerDecorator $em
    ) {
        parent::__construct($label);

        $this->setOption(IconBootstrapRenderer::ICON, 'fas fa-user');

        $this
            ->setRequired('admin-user-edit.form.username.required')
            ->setMaxLength(512)
            ->addRule(
                function (Control $control): bool
                {
                    $value = $control->getValue();
                    $username = is_string($value) ? $value : '';

                    if ($username === '') {
                        return true;
                    }

                    if ($this->checkUsernameExists($username)) {
                        $message = $this->translator
                            ->translate('admin-user-edit.form.username.exists', ['username' => $username]);

                        $this->addError($message);

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

            ->where('LOWER(u.username) = :username')
            ->setParameter('username', mb_strtolower($username));

        if ($this->excludeUserId !== null) {
            $qb
                ->andWhere('u.id != :excludeUserId')
                ->setParameter('excludeUserId', $this->excludeUserId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

}

interface UsernameFormControlFactory
{
    public function create(string $label): UsernameFormControl;
}