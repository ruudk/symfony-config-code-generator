<p align="center">
    <strong>Symfony Config Code Generator</strong><br>
    <em>Transform ContainerBuilder configurations into modern Symfony configuration files with zero effort</em>
</p>
<p align="center">
    <a href="https://packagist.org/packages/ruudk/symfony-config-code-generator"><img src="https://poser.pugx.org/ruudk/symfony-config-code-generator/v?style=for-the-badge" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/ruudk/symfony-config-code-generator"><img src="https://poser.pugx.org/ruudk/symfony-config-code-generator/require/php?style=for-the-badge" alt="PHP Version Require"></a>
    <a href="https://packagist.org/packages/ruudk/symfony-config-code-generator"><img src="https://poser.pugx.org/ruudk/symfony-config-code-generator/downloads?style=for-the-badge" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/ruudk/symfony-config-code-generator"><img src="https://poser.pugx.org/ruudk/symfony-config-code-generator/license?style=for-the-badge" alt="License"></a>
</p>

------

# Symfony Config Code Generator

**Convert your runtime ContainerBuilder into beautiful, production-ready Symfony configuration files!** 

This library bridges the gap between programmatic container building and modern Symfony configuration, making it perfect for migrations, code generation tools, and bundle configuration exports.

## ✨ Why This Library?

Ever needed to convert a dynamically built Symfony container into static configuration files? Migrating from legacy code? Building developer tools that generate Symfony configs? **This is your solution!**

🎯 **Runtime to Config** - Transform ContainerBuilder instances into modern Symfony configuration  
🎯 **Full Feature Support** - Handles all Symfony DI features: autowiring, tags, aliases, decorators, and more  
🎯 **Clean Output** - Generates human-readable configuration using Symfony's best practices  
🎯 **Smart Imports** - Automatically manages function imports and namespaces  
🎯 **Type Safety** - Preserves references, parameters, and expressions correctly  

## 🚀 Key Features

### 🎨 Complete Symfony DI Support
- **Services** - Full service definitions with classes, arguments, and method calls
- **Parameters** - Regular parameters and environment variables
- **References** - Service references, typed references, and inner references
- **Tags** - Service tags with attributes for event listeners, commands, etc.
- **Autowiring & Autoconfigure** - Modern DI features preserved
- **Decorators** - Service decoration with priority support
- **Aliases** - Service and interface aliases
- **Expressions** - Expression language support for dynamic values
- **Tagged Iterators** - Inject collections of tagged services
- **Environment-specific** - Conditional service registration

### 🔧 Smart Code Generation
- **Clean Formatting** - Properly indented, readable output
- **Automatic Imports** - Function imports added automatically
- **Fluent Interface** - Modern configurator syntax
- **Type Preservation** - Maintains type information for better IDE support

## 📦 Installation

Install via Composer:

```bash
composer require ruudk/symfony-config-code-generator --dev
```

## 💡 Usage

Transform your ContainerBuilder into a configuration file in seconds:

<!-- source: examples/example.php -->
```php
<?php

declare(strict_types=1);

include 'vendor/autoload.php';

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
```

### Output

<!-- output: examples/example.php -->
```php
<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\expr;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

// This file was automatically generated and should not be edited.

return static function (ContainerConfigurator $configurator) : void {
    $parameters = $configurator->parameters();
    $parameters->set(
        'app.debug',
        true,
    );
    $parameters->set(
        'database.url',
        env('DATABASE_URL'),
    );

    $services = $configurator->services();

    $services->set(
        'app.feature_service',
        App\Service\FeatureService::class,
    )
        ->args(
            [
                expr('parameter("app.debug") ? "debug" : "production"'),
            ],
        );

    $services->set(
        'app.handler_registry',
        App\Service\HandlerRegistry::class,
    )
        ->args(
            [
                tagged_iterator('app.handler'),
            ],
        );

    $services->set(
        'app.logger',
        Psr\Log\LoggerInterface::class,
    )
        ->autowire();

    $services->set(
        'app.mailer',
        App\Service\MailerService::class,
    )
        ->args(
            [
                service('mailer.transport'),
                param('database.url'),
            ],
        )
        ->call(
            'setLogger',
            [
                service('app.logger'),
            ],
        )
        ->call(
            'configure',
            [
                [
                    'from' => 'noreply@example.com',
                ],
            ],
        );

    $services->set(
        'app.request_listener',
        App\EventListener\RequestListener::class,
    )
        ->tag(
            'kernel.event_listener',
            [
                'event' => 'kernel.request',
                'priority' => 100,
            ],
        );

    $services->set(
        'app.user_handler',
        App\Handler\UserHandler::class,
    )
        ->tag('app.handler');
};
```

## 🎯 Perfect For

- **Legacy Migration** - Convert old bundle configurations to modern format
- **Code Generation** - Build tools that output Symfony configurations
- **Configuration Export** - Export runtime container state for debugging
- **Bundle Development** - Generate configuration examples from code
- **Testing** - Verify container configurations programmatically

## 🏗️ Built With

This library is powered by [ruudk/code-generator](https://github.com/ruudk/code-generator), providing robust code generation capabilities with automatic formatting and imports.

## 💖 Support This Project

Love this tool? Help me keep building awesome open source software!

[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-pink)](https://github.com/sponsors/ruudk)

Your sponsorship helps me dedicate more time to maintaining and improving this project. Every contribution, no matter the size, makes a difference!

## 🤝 Contributing

I welcome contributions! Whether it's a bug fix, new feature, or documentation improvement, I'd love to see your PRs.

## 📄 License

MIT License – Free to use in your projects! If you're using this and finding value, please consider [sponsoring](https://github.com/sponsors/ruudk) to support continued development.