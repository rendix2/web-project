<?php declare(strict_types=1);

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
// OPRAVA: Používáme správnou třídu a metody pro VerbosityLevel
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<MethodCall>
 */
final class FlashMessageStdClassRule implements Rule
{
    public function getNodeType() : string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope) : array
    {
        if (!$node->name instanceof Node\Identifier || $node->name->toString() !== 'flashMessage') {
            return [];
        }

        $callerType = $scope->getType($node->var);
        $presenterType = new ObjectType('Nette\Application\UI\Presenter');
        $controlType = new ObjectType('Nette\Application\UI\Control');

        $isPresenter = $presenterType->isSuperTypeOf($callerType)->yes();
        $isControl = $controlType->isSuperTypeOf($callerType)->yes();

        if (!$isPresenter && !$isControl) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) === 0) {
            return [];
        }

        $messageArgumentType = $scope->getType($args[0]->value);
        if ($messageArgumentType->isString()->yes()) {
            return [
                RuleErrorBuilder::message(
                    'Method flashMessage() should accept first parameter of type \stdClass. flashMessage() was called with string parameter.'
                )
                    ->identifier('flashMessage.stringNotStdClass')
                    ->build(),
            ];
        }

        return [];
    }
}
