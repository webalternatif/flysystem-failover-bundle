<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'vendor'])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PhpCsFixer' => true,
        '@PER-CS' => true,
        '@Symfony' => true,
        'declare_strict_types' => true,
        'php_unit_method_casing' => ['case' => 'snake_case'],
    ])
    ->setFinder($finder)
;
