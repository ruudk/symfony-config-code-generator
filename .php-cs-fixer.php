<?php

declare(strict_types=1);

use PhpCsFixer\Finder;
use Ticketswap\PhpCsFixerConfig\PhpCsFixerConfigFactory;
use Ticketswap\PhpCsFixerConfig\RuleSet\TicketSwapRuleSet;

$finder = Finder::create()
    ->in(__DIR__ . '/examples')
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->append([
        __DIR__ . '/.php-cs-fixer.php',
        __DIR__ . '/phpstan.php',
        __DIR__ . '/composer-dependency-analyser.php',
    ]);

return PhpCsFixerConfigFactory::create(TicketSwapRuleSet::create())->setFinder($finder);
