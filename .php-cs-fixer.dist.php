<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->exclude('examples')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'no_unused_imports' => true,
        'full_opening_tag' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/var/.php-cs-fixer.cache')
;
