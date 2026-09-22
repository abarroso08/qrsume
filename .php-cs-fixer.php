<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['vendor', 'phpqrcode', 'images', 'blocks'])
    ->name('*.php')
    ->notPath('local_dev_seed.sql');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(false);
