<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()->in(__DIR__.'/src');

return (new Config)
    ->setRiskyAllowed(true)
    ->setRules([
        'native_function_invocation' => true,
    ])
    ->setFinder($finder)
    ->setUsingCache(false)
    ->setUnsupportedPhpVersionAllowed(true);
