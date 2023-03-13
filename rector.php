<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/config',
        __DIR__.'/src',
        __DIR__.'/tests',
    ]);

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::EARLY_RETURN,
        SetList::DEAD_CODE,
        SetList::PSR_4,
        LevelSetList::UP_TO_PHP_72,
    ]);

    $rectorConfig->rules([
        \Rector\CodingStyle\Rector\MethodCall\PreferThisOrSelfMethodCallRector::class,
        \Rector\CodingStyle\Rector\ClassMethod\DataProviderArrayItemsNewlinedRector::class,
        \Rector\TypeDeclaration\Rector\ClassMethod\ArrayShapeFromConstantArrayReturnRector::class,
    ]);

    $rectorConfig->skip([
        \Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector::class,
        \Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector::class,
        \Rector\TypeDeclaration\Rector\ClassMethod\ArrayShapeFromConstantArrayReturnRector::class => [
            __DIR__ . '/src/DataMapper/ResponseDataMapper',
        ],
    ]);

    $rectorConfig->ruleWithConfiguration(\Rector\CodingStyle\Rector\FuncCall\ConsistentPregDelimiterRector::class, [
        \Rector\CodingStyle\Rector\FuncCall\ConsistentPregDelimiterRector::DELIMITER => '/',
    ]);

    $rectorConfig->ruleWithConfiguration(\Rector\CodingStyle\Rector\Property\InlineSimplePropertyAnnotationRector::class, [
        'var',
        'phpstan-var',
        'property',
    ]);
};
