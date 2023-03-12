<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/config',
        __DIR__.'/src',
        __DIR__.'/tests',
    ]);

    $rectorConfig->sets([
        \Rector\Set\ValueObject\SetList::CODE_QUALITY,
        \Rector\Set\ValueObject\SetList::CODING_STYLE,
        \Rector\Set\ValueObject\SetList::EARLY_RETURN,
        \Rector\Set\ValueObject\SetList::PSR_4,
        \Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_72,
    ]);

    $rectorConfig->rules([
        \Rector\CodingStyle\Rector\MethodCall\PreferThisOrSelfMethodCallRector::class,
        \Rector\CodingStyle\Rector\ClassMethod\DataProviderArrayItemsNewlinedRector::class,

        // DEAD_CODE rules
        \Rector\DeadCode\Rector\Cast\RecastingRemovalRector::class,
        \Rector\DeadCode\Rector\BooleanAnd\RemoveAndTrueRector::class,
        \Rector\DeadCode\Rector\Concat\RemoveConcatAutocastRector::class,
        \Rector\DeadCode\Rector\Return_\RemoveDeadConditionAboveReturnRector::class,
        \Rector\DeadCode\Rector\Assign\RemoveDoubleAssignRector::class,
        \Rector\DeadCode\Rector\Array_\RemoveDuplicatedArrayKeyRector::class,
        \Rector\DeadCode\Rector\FunctionLike\RemoveDuplicatedIfReturnRector::class,
        \Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector::class,
        \Rector\DeadCode\Rector\Stmt\RemoveUnreachableStatementRector::class,
        \Rector\DeadCode\Rector\Foreach_\RemoveUnusedForeachKeyRector::class,
        \Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector::class,
        \Rector\DeadCode\Rector\If_\RemoveUnusedNonEmptyArrayBeforeForeachRector::class,
        \Rector\DeadCode\Rector\If_\SimplifyIfElseWithSameContentRector::class,
        \Rector\DeadCode\Rector\Ternary\TernaryToBooleanOrFalseToBooleanAndRector::class,
        \Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector::class,

        \Rector\TypeDeclaration\Rector\ClassMethod\ArrayShapeFromConstantArrayReturnRector::class,
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
