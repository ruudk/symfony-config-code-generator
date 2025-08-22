<?php

declare(strict_types=1);

include '../vendor/autoload.php';

use Ruudk\SymfonyConfigCodeGenerator\SymfonyConfigCodeGenerator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

$container = new ContainerBuilder();

// Parameters
$container->setParameter('app.debug', true);
$container->setParameter('database.url', '%env(DATABASE_URL)%');

// Simple service with autowiring
$container->register('app.logger', 'Psr\Log\LoggerInterface')
    ->setAutowired(true);

// Service with arguments and method calls
$container->register('app.mailer', 'App\Service\MailerService')
    ->addArgument(new Reference('mailer.transport'))
    ->addArgument('%database.url%')
    ->addMethodCall('setLogger', [new Reference('app.logger')])
    ->addMethodCall('configure', [[
        'from' => 'noreply@example.com',
    ]]);

// Event listener with tags
$container->register('app.request_listener', 'App\EventListener\RequestListener')
    ->addTag('kernel.event_listener', [
        'event' => 'kernel.request',
        'priority' => 100,
    ]);

// Service with expression
$container->register('app.feature_service', 'App\Service\FeatureService')
    ->addArgument(new Expression('parameter("app.debug") ? "debug" : "production"'));

// Service with tagged iterator
$container->register('app.handler_registry', 'App\Service\HandlerRegistry')
    ->addArgument(new TaggedIteratorArgument('app.handler'));

// Tagged services
$container->register('app.user_handler', 'App\Handler\UserHandler')
    ->addTag('app.handler');

// Generate the configuration
echo new SymfonyConfigCodeGenerator()->dumpFile($container);
