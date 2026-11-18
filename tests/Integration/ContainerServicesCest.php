<?php declare(strict_types=1);

namespace Tests\Integration;

use Closure;
use Nette\DI\Container;
use ReflectionObject;
use Tests\Support\IntegrationTester;
use Throwable;

/**
 * Every service of the application (a class of the `App\` namespace in the DI container) can be created and gets EXPANDED
 * parameters: no string the created service holds is a "%name%" reference to a parameter (in alfred '%mediaDir%' once literally
 * became a directory in the document root). From alfred (ContainerServicesCest, 2026-11-18).
 *
 * The services come from the compiled container (Container::getServiceDescriptors()); creating one must not call the network,
 * so no service is allowed to fail.
 */
class ContainerServicesCest
{
    private const string UNEXPANDED = '~%[A-Za-z_][\w.]*%~';

    public function everyServiceOfTheApplicationCanBeCreatedWithExpandedParameters(IntegrationTester $I) : void
    {
        $container = $I->grabService(Container::class);

        $names = [];

        foreach ($container->getServiceDescriptors() as $descriptor) {
            if ($descriptor->type !== null && str_starts_with($descriptor->type, 'App\\')) {
                $names[] = $descriptor->name;
            }
        }

        $I->assertGreaterThan(20, count($names), 'The container has the services of the application.');

        $failures = [];
        $unexpanded = [];

        foreach ($names as $name) {
            try {
                $service = $container->getByName($name);
            } catch (Throwable $e) {
                $failures[$name] = $e::class . ': ' . $e->getMessage();
                continue;
            }

            foreach ($this->stringsOf($service) as $string) {
                if (preg_match(self::UNEXPANDED, $string) === 1) {
                    $unexpanded[$name][] = $string;
                }
            }
        }

        $I->assertSame([], $failures, 'Services that cannot be created: ' . $this->dump($failures));
        $I->assertSame([], $unexpanded, 'Services with an unexpanded %parameter%: ' . $this->dump($unexpanded));
    }

    /**
     * The strings held by the service: its properties of any visibility and the arrays in them (no nested objects - they are
     * services of their own or library objects; closures and the container are skipped).
     *
     * @return list<string>
     */
    private function stringsOf(object $service) : array
    {
        $strings = [];

        foreach ((new ReflectionObject($service))->getProperties() as $property) {
            if ($property->isStatic() || !$property->isInitialized($service)) {
                continue;
            }

            $this->collect($property->getValue($service), $strings);
        }

        return $strings;
    }

    /**
     * @param list<string> $strings
     */
    private function collect(mixed $value, array &$strings) : void
    {
        if (is_string($value)) {
            $strings[] = $value;
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                if (!$item instanceof Container && !$item instanceof Closure) {
                    $this->collect($item, $strings);
                }
            }
        }
    }

    private function dump(mixed $value) : string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
