<?php

declare(strict_types=1);

use ESoul\CodingStandard\Config;
use PhpCsFixer\Finder;

$config = Config::create(
    Finder::create()->files()->in([__DIR__ . '/src', __DIR__ . '/config', __DIR__ . '/tests'])->append([__FILE__]),
    __DIR__ . '/cache/php-cs-fixer.cache',
);

// Keep the parentheses around `(new Foo())->bar()`: dropping them is PHP 8.4+ syntax and the package supports PHP 8.3.
// void_return is off: public methods of extensible classes must not gain return types (a BC break for subclasses).
return $config->setRules([
    ...Config::rules(),
    'new_expression_parentheses' => ['use_parentheses' => true],
    'void_return' => false,
]);
