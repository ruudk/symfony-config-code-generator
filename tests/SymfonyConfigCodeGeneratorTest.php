<?php

declare(strict_types=1);

namespace Ruudk\SymfonyConfigCodeGenerator;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\ExpressionLanguage\Expression;

#[CoversClass(SymfonyConfigCodeGenerator::class)]
final class SymfonyConfigCodeGeneratorTest extends TestCase
{
    private SymfonyConfigCodeGenerator $generator;

    #[Override]
    protected function setUp() : void
    {
        parent::setUp();

        $this->generator = new SymfonyConfigCodeGenerator();
    }

    private function assertDumpFile(string $expected, ContainerBuilder $container) : void
    {
        self::assertSame($expected, $this->generator->dumpFile($container));
    }

    public function testDumpFileWithEmptyContainer() : void
    {
        $container = new ContainerBuilder();

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $services = $configurator->services();
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithParameters() : void
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.name', 'My Application');
        $container->setParameter('app.debug', true);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $parameters = $configurator->parameters();
                    $parameters->set(
                        'app.name',
                        'My Application',
                    );
                    $parameters->set(
                        'app.debug',
                        true,
                    );

                    $services = $configurator->services();
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithService() : void
    {
        $container = new ContainerBuilder();
        $container->register('app.logger', 'Psr\Log\LoggerInterface')
            ->setAutowired(true);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $services = $configurator->services();

                    $services->set(
                        'app.logger',
                        \Psr\Log\LoggerInterface::class,
                    )
                        ->autowire();
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithServiceArguments() : void
    {
        $container = new ContainerBuilder();
        $container->register('app.mailer', 'App\Mailer\MailerService')
            ->addArgument(new Reference('mailer.transport'))
            ->addArgument('%mailer.from%')
            ->addArgument([
                'timeout' => 30,
                'retries' => 3,
            ]);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
                use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
                use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $services = $configurator->services();

                    $services->set(
                        'app.mailer',
                        \App\Mailer\MailerService::class,
                    )
                        ->args(
                            [
                                service('mailer.transport'),
                                param('mailer.from'),
                                [
                                    'timeout' => 30,
                                    'retries' => 3,
                                ],
                            ],
                        );
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithMethodCalls() : void
    {
        $container = new ContainerBuilder();
        $container->register('app.listener', 'App\EventListener\UserListener')
            ->addMethodCall('setLogger', [new Reference('logger')])
            ->addMethodCall('setDebug', [true]);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
                use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $services = $configurator->services();

                    $services->set(
                        'app.listener',
                        \App\EventListener\UserListener::class,
                    )
                        ->call(
                            'setLogger',
                            [
                                service('logger'),
                            ],
                        )
                        ->call(
                            'setDebug',
                            [
                                true,
                            ],
                        );
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithTags() : void
    {
        $container = new ContainerBuilder();
        $container->register('app.event_listener', 'App\EventListener\RequestListener')
            ->addTag('kernel.event_listener', [
                'event' => 'kernel.request',
                'priority' => 100,
            ])
            ->addTag('kernel.event_listener', [
                'event' => 'kernel.response',
            ]);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $services = $configurator->services();

                    $services->set(
                        'app.event_listener',
                        \App\EventListener\RequestListener::class,
                    )
                        ->tag(
                            'kernel.event_listener',
                            [
                                'event' => 'kernel.request',
                                'priority' => 100,
                            ],
                        )
                        ->tag(
                            'kernel.event_listener',
                            [
                                'event' => 'kernel.response',
                            ],
                        );
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithAutoconfigure() : void
    {
        $container = new ContainerBuilder();
        $container->register('app.command', 'App\Command\ProcessCommand')
            ->setAutoconfigured(true)
            ->addTag('console.command');

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $services = $configurator->services();

                    $services->set(
                        'app.command',
                        \App\Command\ProcessCommand::class,
                    )
                        ->tag('console.command')
                        ->autoconfigure();
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithExpression() : void
    {
        $container = new ContainerBuilder();
        $container->register('app.service', 'App\Service\DynamicService')
            ->addArgument(new Expression('service("app.config").get("enabled")'));

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
                use function Symfony\Component\DependencyInjection\Loader\Configurator\expr;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $services = $configurator->services();

                    $services->set(
                        'app.service',
                        \App\Service\DynamicService::class,
                    )
                        ->args(
                            [
                                expr('service("app.config").get("enabled")'),
                            ],
                        );
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithParametersUsingEnv() : void
    {
        $container = new ContainerBuilder();
        $container->setParameter('database_url', '%env(DATABASE_URL)%');
        $container->setParameter('app_secret', '%env(APP_SECRET)%');

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
                use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $parameters = $configurator->parameters();
                    $parameters->set(
                        'database_url',
                        env('DATABASE_URL'),
                    );
                    $parameters->set(
                        'app_secret',
                        env('APP_SECRET'),
                    );

                    $services = $configurator->services();
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithComplexService() : void
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.debug', true);

        $definition = $container->register('app.complex', 'App\Service\ComplexService')
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addArgument(new Reference('logger'))
            ->addArgument('%app.debug%')
            ->addMethodCall('configure', [[
                'cache' => true,
            ]])
            ->addTag('app.service', [
                'priority' => 10,
            ]);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
                use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
                use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $parameters = $configurator->parameters();
                    $parameters->set(
                        'app.debug',
                        true,
                    );

                    $services = $configurator->services();

                    $services->set(
                        'app.complex',
                        \App\Service\ComplexService::class,
                    )
                        ->args(
                            [
                                service('logger'),
                                param('app.debug'),
                            ],
                        )
                        ->call(
                            'configure',
                            [
                                [
                                    'cache' => true,
                                ],
                            ],
                        )
                        ->tag(
                            'app.service',
                            [
                                'priority' => 10,
                            ],
                        )
                        ->autoconfigure()
                        ->autowire();
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithServiceDecoration() : void
    {
        $container = new ContainerBuilder();
        $container->register('app.mailer_decorator', 'App\Mailer\DecoratedMailer')
            ->setDecoratedService('mailer');

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $services = $configurator->services();

                    $services->set(
                        'app.mailer_decorator',
                        \App\Mailer\DecoratedMailer::class,
                    )
                        ->decorate('mailer');
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithTypedReference() : void
    {
        $container = new ContainerBuilder();
        $container->register('app.handler', 'App\Handler\MessageHandler')
            ->addArgument(new TypedReference('Psr\Log\LoggerInterface', 'Psr\Log\LoggerInterface', ContainerBuilder::EXCEPTION_ON_INVALID_REFERENCE, 'logger'));

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
                use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $services = $configurator->services();

                    $services->set(
                        'app.handler',
                        \App\Handler\MessageHandler::class,
                    )
                        ->args(
                            [
                                service(\Psr\Log\LoggerInterface::class . ' $logger'),
                            ],
                        );
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithServiceAlias() : void
    {
        $container = new ContainerBuilder();

        // Create a service with an alias tag
        $definition = $container->register('app.custom_logger', 'App\Logger\CustomLogger');
        $definition->addTag('container.alias', [
            'alias' => 'Psr\Log\LoggerInterface',
        ]);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $services = $configurator->services();

                    $services->set(
                        'app.custom_logger',
                        \App\Logger\CustomLogger::class,
                    );

                    $services->alias(
                        \Psr\Log\LoggerInterface::class,
                        'app.custom_logger',
                    );
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileWithEnvironmentSpecificService() : void
    {
        $container = new ContainerBuilder();

        // Create a service with environment-specific configuration
        $definition = $container->register('app.debug_logger', 'App\Logger\DebugLogger');
        $definition->addTag('container.env', [
            'env' => 'dev',
        ]);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $services = $configurator->services();

                    if ($configurator->env() === 'dev') {
                        $services->set(
                            'app.debug_logger',
                            \App\Logger\DebugLogger::class,
                        );
                    }
                };

                PHP,
            $container,
        );
    }

    public function testDumpFileSkipsServiceContainer() : void
    {
        $container = new ContainerBuilder();
        // The 'service_container' service should be skipped
        $container->register('service_container', 'Symfony\Component\DependencyInjection\Container');
        $container->register('app.service', 'App\Service\MyService');

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

                // This file was automatically generated and should not be edited.

                return static function (ContainerConfigurator $configurator) : void {
                    $services = $configurator->services();

                    $services->set(
                        'app.service',
                        \App\Service\MyService::class,
                    );
                };

                PHP,
            $container,
        );
    }
}
