<?php

declare(strict_types=1);

namespace Ruudk\SymfonyConfigCodeGenerator;

use Exception;
use Generator;
use InvalidArgumentException;
use Ruudk\CodeGenerator\CodeGenerator;
use Ruudk\CodeGenerator\FunctionName;
use Ruudk\CodeGenerator\Group;
use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\ExpressionLanguage\Expression;
use Throwable;
use UnitEnum;

/**
 * @phpstan-import-type CodeLine from CodeGenerator
 * @phpstan-import-type CodeLines from CodeGenerator
 */
#[Exclude]
final readonly class SymfonyConfigCodeGenerator
{
    public function __construct(
        private CodeGenerator $generator = new CodeGenerator(),
    ) {}

    /**
     * @throws Exception
     */
    public function dumpFile(ContainerBuilder $container): string
    {
        return $this->generator->dumpFile([
            '// This file was automatically generated and should not be edited.',
            '',
            sprintf(
                'return static function (%s $configurator) : void {',
                $this->generator->import(ContainerConfigurator::class),
            ),
            $this->generator->indent($this->dump($container)),
            '};',
        ]);
    }

    /**
     * @throws Exception
     * @return Generator<CodeLine>
     */
    public function dump(ContainerBuilder $container): Generator
    {
        $parameters = $container->getParameterBag()->all();

        if ($parameters !== []) {
            yield '$parameters = $configurator->parameters();';

            foreach ($parameters as $name => $value) {
                yield from $this->generator->suffixLast(';', $this->generator->dumpCall('$parameters', 'set', [
                    $this->export($name),
                    $this->export($value),
                ]));
            }

            yield '';
        }

        yield '$services = $configurator->services();';

        $definitions = $container->getDefinitions();
        ksort($definitions);

        foreach ($definitions as $id => $definition) {
            if ($id === 'service_container') {
                continue;
            }

            try {
                yield '';
                yield from $this->dumpDefinition($id, $definition);
            } catch (Throwable $error) {
                throw new Exception(sprintf('Failed dumping service "%s": %s', $id, $error->getMessage()), 0, $error);
            }
        }
    }

    /**
     * @return Generator<CodeLine>
     */
    private function dumpDefinition(string $id, Definition $definition): Generator
    {
        $tags = $definition->getTags();

        $envs = [];
        foreach ($this->getTagAttributes($tags, 'container.env') as $attributes) {
            $envs[] = $this->getStringAttribute($attributes, 'env');
        }

        sort($envs);

        $aliases = [];
        foreach ($this->getTagAttributes($tags, 'container.alias') as $attributes) {
            $alias = $this->getStringAttribute($attributes, 'alias');
            $aliases[] = ($attributes['target'] ?? null) !== null
                ? sprintf('%s $%s', $alias, $this->getStringAttribute($attributes, 'target'))
                : $alias;
        }

        sort($aliases);

        if (array_key_exists('container.env', $tags) || array_key_exists('container.alias', $tags)) {
            unset($tags['container.env'], $tags['container.alias']);
            $definition->setTags($tags);
        }

        $code = new Group(function () use ($aliases, $id, $definition) {
            yield from $this->generator->suffixLast(';', $this->dumpService($id, $definition));

            foreach ($aliases as $alias) {
                yield '';
                yield from $this->generator->suffixLast(';', $this->generator->dumpCall('$services', 'alias', [
                    $this->export($alias),
                    $this->export($id),
                ]));
            }
        });

        if ($envs === []) {
            yield $code;

            return;
        }

        if (count($envs) > 1) {
            yield sprintf('if (in_array($configurator->env(), [%s], true)) {', implode(', ', array_map(
                static fn(string $env): string => var_export($env, true),
                $envs,
            )));
        } else {
            yield sprintf('if ($configurator->env() === %s) {', var_export($envs[0], true));
        }

        yield $this->generator->indent($code->lines);
        yield '}';
    }

    /**
     * @return Generator<CodeLine>
     */
    private function dumpService(string $id, Definition $definition): Generator
    {
        $class = $definition->getClass();

        if ($class !== null && $class !== $id) {
            $args = [
                var_export($id, true),
                $this->generator->dumpClassReference($class, false),
            ];
        } else {
            $args = [$this->generator->dumpClassReference($id, false)];
        }

        yield from $this->generator->dumpCall('$services', 'set', $args);

        $group = $this->generator->indent($this->dumpServiceConfiguration($definition));

        if (!$group->isEmpty()) {
            yield $group;
        }
    }

    /**
     * @return Generator<CodeLine>
     */
    private function dumpServiceConfiguration(Definition $definition): Generator
    {
        if ($definition->getArguments() !== []) {
            yield '->args(';
            yield $this->generator->indent(function () use ($definition) {
                yield from $this->generator->suffixLast(',', $this->export($definition->getArguments()));
            });
            yield ')';
        }

        foreach ($definition->getMethodCalls() as $methodCall) {
            [$method, $arguments] = $this->toArray($methodCall);
            yield from $this->generator->dumpCall('', 'call', [
                $this->export($method),
                $this->export($arguments),
            ]);
        }

        $decoratedService = $definition->getDecoratedService();

        if ($decoratedService !== null) {
            yield from $this->generator->dumpCall('', 'decorate', [
                $this->export($decoratedService[0]),
            ]);
        }

        foreach ($definition->getTags() as $name => $tags) {
            foreach (array_unique($this->toArray($tags), SORT_REGULAR) as $attributes) {
                if ($attributes === []) {
                    yield from $this->generator->dumpCall('', 'tag', [
                        $this->export($name),
                    ]);

                    continue;
                }

                yield from $this->generator->dumpCall('', 'tag', [
                    $this->export($name),
                    $this->export($attributes),
                ]);
            }
        }

        if ($definition->isAutoconfigured()) {
            yield from $this->generator->dumpCall('', 'autoconfigure');
        }

        if ($definition->isAutowired()) {
            yield from $this->generator->dumpCall('', 'autowire');
        }
    }

    /**
     * @param array<array-key, mixed> $tags
     *
     * @return list<array<array-key, mixed>>
     */
    private function getTagAttributes(array $tags, string $name): array
    {
        if (!array_key_exists($name, $tags)) {
            return [];
        }

        return array_values(array_map($this->toArray(...), $this->toArray($tags[$name])));
    }

    /**
     * @param array<array-key, mixed> $attributes
     */
    private function getStringAttribute(array $attributes, string $name): string
    {
        $value = $attributes[$name] ?? null;

        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Expected tag attribute "%s" to be a string, got "%s".',
                $name,
                get_debug_type($value),
            ));
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function toArray(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Expected array, got "%s".', get_debug_type($value)));
        }

        return $value;
    }

    private function fqcnExists(string $fqcn): bool
    {
        return class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn);
    }

    private function isFqcn(string $fqcn): bool
    {
        return preg_match('/^[A-Z][a-zA-Z0-9_]*(\\\\[A-Z][a-zA-Z0-9_]*)+$/', $fqcn) === 1;
    }

    /**
     * @return Generator<CodeLine>
     */
    private function export(mixed $input): Generator
    {
        if ($input === null) {
            yield 'null';
        } elseif (is_string($input) && str_starts_with($input, '%env(') && str_ends_with($input, ')%')) {
            yield from $this->dumpEnv(substr($input, 5, -2));
        } elseif (is_string($input) && str_starts_with($input, '%') && str_ends_with($input, '%')) {
            yield from $this->dumpParam(substr($input, 1, -1));
        } elseif (is_string($input) && ($this->isFqcn($input) || $this->fqcnExists($input))) {
            // The class could not yet exist at compile time, so we need to check if it starts with TicketSwap\
            yield $input . '::class';
        } elseif (is_scalar($input)) {
            if (is_string($input) && preg_match('/^(?<fqcn>[\w\\\\]+) \$(?<name>\w+)$/', $input, $matches) === 1) {
                yield sprintf("%s::class . ' \$%s'", $matches['fqcn'], $matches['name']);
            } else {
                yield var_export($input, true);
            }
        } elseif ($input instanceof Reference) {
            yield from $this->dumpReference($input);
        } elseif ($input instanceof ServiceClosureArgument) {
            yield from $this->dumpServiceClosure((string) $input->getValues()[0]);
        } elseif ($input instanceof ServiceLocatorArgument) {
            yield from $this->dumpServiceLocator($input);
        } elseif ($input instanceof TaggedIteratorArgument) {
            yield from $this->dumpTaggedIterator($input);
        } elseif ($input instanceof IteratorArgument) {
            yield from $this->dumpIterator($input);
        } elseif ($input instanceof Expression) {
            yield from $this->dumpExpression($input);
        } elseif ($input instanceof ArgumentInterface) {
            throw new InvalidArgumentException(sprintf(
                'Cannot export ArgumentInterface of type "%s".',
                get_debug_type($input),
            ));
        } elseif ($input instanceof Autowire) {
            yield from $this->export($input->value);
        } elseif ($input instanceof Definition) {
            yield from $this->dumpInlineService($input);
        } elseif ($input instanceof UnitEnum) {
            yield $input::class . '::' . $input->name;
        } elseif (is_array($input)) {
            if ($input === []) {
                yield '[]';

                return;
            }

            yield new Group(function () use ($input) {
                yield '[';
                yield $this->generator->indent(function () use ($input) {
                    $isList = array_is_list($input);
                    foreach ($input as $key => $value) {
                        yield from $this->generator->suffixLast(
                            ',',
                            $isList
                                ? $this->export($value)
                                : $this->generator->joinFirstPair(function () use ($value, $key) {
                                    yield from $this->generator->suffixFirst(' => ', $this->export($key));
                                    yield from $this->export($value);
                                }),
                        );
                    }
                });
                yield ']';
            });
        } else {
            throw new InvalidArgumentException(sprintf('Cannot export value of type "%s".', get_debug_type($input)));
        }
    }

    /**
     * @return Generator<CodeLine>
     */
    private function dumpReference(Reference $value): Generator
    {
        $id = $value instanceof TypedReference ? sprintf('%s $%s', $value, $value->getName()) : (string) $value;

        $call = $this->generator->dumpFunctionCall(
            $this->generator->import(
                new FunctionName('Symfony\Component\DependencyInjection\Loader\Configurator\service'),
            ),
            [
                $this->export($id),
            ],
        );

        yield from match ($value->getInvalidBehavior()) {
            ContainerInterface::IGNORE_ON_INVALID_REFERENCE => $this->generator->dumpCall($call, 'ignoreOnInvalid'),
            ContainerInterface::NULL_ON_INVALID_REFERENCE => $this->generator->dumpCall($call, 'nullOnInvalid'),
            default => $call,
        };
    }

    /**
     * @return Generator<CodeLine>
     */
    private function dumpTaggedIterator(TaggedIteratorArgument $value, string $function = 'tagged_iterator'): Generator
    {
        $args = [
            $this->export($value->getTag()),
        ];

        $indexAttribute = $value->getIndexAttribute();

        if ($indexAttribute === $value->getTag()) {
            $indexAttribute = 'index';
        }

        $defaultValuedArgument = new TaggedIteratorArgument($value->getTag(), needsIndexes: true);

        if ($value->getIndexAttribute() !== null) {
            $args[] = $this->generator->prefixFirst('indexAttribute: ', $this->export($indexAttribute));
        }

        [$defaultIndexMethod, $defaultPriorityMethod] = $this->getDefaultMethods($value);
        [$defaultDefaultIndexMethod, $defaultDefaultPriorityMethod] = $this->getDefaultMethods($defaultValuedArgument);

        if ($defaultIndexMethod !== null && $defaultIndexMethod !== $defaultDefaultIndexMethod) {
            $args[] = $this->generator->prefixFirst('defaultIndexMethod: ', $this->export($defaultIndexMethod));
        }

        if ($defaultPriorityMethod !== null && $defaultPriorityMethod !== $defaultDefaultPriorityMethod) {
            $args[] = $this->generator->prefixFirst('defaultPriorityMethod: ', $this->export($defaultPriorityMethod));
        }

        if ($value->getExclude() !== []) {
            $args[] = $this->generator->prefixFirst('exclude: ', $this->export($value->getExclude()));
        }

        if (!$value->excludeSelf()) {
            $args[] = $this->generator->prefixFirst('excludeSelf: ', $this->export(false));
        }

        yield from $this->generator->dumpFunctionCall(
            $this->generator->import(new FunctionName(sprintf(
                'Symfony\Component\DependencyInjection\Loader\Configurator\%s',
                $function,
            ))),
            $args,
        );
    }

    /**
     * Symfony 8.1 deprecated the default index and priority methods in favor of #[AsTaggedItem]. Passing `false`
     * reads them without triggering that deprecation; older Symfony versions ignore the argument.
     *
     * @return array{?string, ?string}
     */
    private function getDefaultMethods(TaggedIteratorArgument $argument): array
    {
        // @mago-expect analysis:deprecated-method
        $defaultIndexMethod = $argument->getDefaultIndexMethod(false);
        // @mago-expect analysis:deprecated-method
        $defaultPriorityMethod = $argument->getDefaultPriorityMethod(false);

        return [$defaultIndexMethod, $defaultPriorityMethod];
    }

    /**
     * @return Generator<CodeLine>
     */
    private function dumpIterator(IteratorArgument $value): Generator
    {
        yield from $this->generator->dumpFunctionCall(
            $this->generator->import(
                new FunctionName('Symfony\Component\DependencyInjection\Loader\Configurator\iterator'),
            ),
            [
                $this->export($value->getValues()),
            ],
        );
    }

    /**
     * @return Generator<CodeLine>
     */
    private function dumpEnv(string $name): Generator
    {
        yield from $this->generator->dumpFunctionCall(
            $this->generator->import(new FunctionName('Symfony\Component\DependencyInjection\Loader\Configurator\env')),
            $this->export($name),
        );
    }

    /**
     * @return Generator<CodeLine>
     */
    private function dumpParam(string $name): Generator
    {
        yield from $this->generator->dumpFunctionCall(
            $this->generator->import(
                new FunctionName('Symfony\Component\DependencyInjection\Loader\Configurator\param'),
            ),
            $this->export($name),
        );
    }

    /**
     * @return Generator<CodeLine>
     */
    private function dumpServiceClosure(string $value): Generator
    {
        yield from $this->generator->dumpFunctionCall(
            $this->generator->import(
                new FunctionName('Symfony\Component\DependencyInjection\Loader\Configurator\service_closure'),
            ),
            $this->export($value),
        );
    }

    /**
     * @return Generator<CodeLine>
     */
    private function dumpServiceLocator(ServiceLocatorArgument $input): Generator
    {
        $taggedIteratorArgument = $input->getTaggedIteratorArgument();

        if ($taggedIteratorArgument !== null) {
            yield from $this->dumpTaggedIterator($taggedIteratorArgument, 'tagged_locator');

            return;
        }

        yield from $this->generator->dumpFunctionCall(
            $this->generator->import(
                new FunctionName('Symfony\Component\DependencyInjection\Loader\Configurator\service_locator'),
            ),
            $this->export($input->getValues()),
        );
    }

    /**
     * @return Generator<CodeLine>
     */
    private function dumpInlineService(Definition $service): Generator
    {
        $call = $this->generator->dumpFunctionCall(
            $this->generator->import(
                new FunctionName('Symfony\Component\DependencyInjection\Loader\Configurator\inline_service'),
            ),
            $this->export($service->getClass()),
        );

        if ($service->getArguments() !== []) {
            $call = $this->generator->dumpCall($call, 'args', function () use ($service) {
                yield from $this->export($service->getArguments());
            });
        }

        $decoratedService = $service->getDecoratedService();

        if ($decoratedService !== null) {
            $call = $this->generator->dumpCall($call, 'decorate', [
                $this->export($decoratedService[0]),
            ]);
        }

        $sortedTags = $service->getTags();
        ksort($sortedTags);

        foreach ($sortedTags as $name => $tags) {
            foreach ($this->toArray($tags) as $attributes) {
                if ($attributes === []) {
                    $call = $this->generator->dumpCall($call, 'tag', [
                        $this->export($name),
                    ]);

                    continue;
                }

                $call = $this->generator->dumpCall($call, 'tag', [
                    $this->export($name),
                    $this->export($attributes),
                ]);
            }
        }

        if ($service->isAutoconfigured()) {
            $call = $this->generator->dumpCall($call, 'autoconfigure');
        }

        if ($service->isAutowired()) {
            $call = $this->generator->dumpCall($call, 'autowire');
        }

        yield from $call;
    }

    /**
     * @return Generator<CodeLine>
     */
    private function dumpExpression(Expression $expression): Generator
    {
        yield from $this->generator->dumpFunctionCall(
            $this->generator->import(
                new FunctionName('Symfony\Component\DependencyInjection\Loader\Configurator\expr'),
            ),
            $this->export((string) $expression),
        );
    }
}
