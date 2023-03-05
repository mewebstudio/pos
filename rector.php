<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/config',
        __DIR__.'/src',
        __DIR__.'/tests',
    ]);

    $rectorConfig->rules([
        \Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector::class,
        \Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector::class,
        \Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector::class,
        \Rector\CodeQuality\Rector\ClassMethod\OptionalParametersAfterRequiredRector::class,
        \Rector\CodeQuality\Rector\ClassMethod\ReturnTypeFromStrictScalarReturnExprRector::class,
        \Rector\CodeQuality\Rector\FuncCall\ArrayKeysAndInArrayToArrayKeyExistsRector::class,
        \Rector\CodeQuality\Rector\FuncCall\ArrayMergeOfNonArraysToSimpleArrayRector::class,
        \Rector\CodeQuality\Rector\FuncCall\BoolvalToTypeCastRector::class,
        \Rector\CodeQuality\Rector\FuncCall\IntvalToTypeCastRector::class,
        \Rector\CodeQuality\Rector\FuncCall\ChangeArrayPushToArrayAssignRector::class,
        \Rector\CodeQuality\Rector\FuncCall\SimplifyInArrayValuesRector::class,
        \Rector\CodeQuality\Rector\FuncCall\StrvalToTypeCastRector::class,
        \Rector\CodeQuality\Rector\FunctionLike\RemoveAlwaysTrueConditionSetInConstructorRector::class,
        \Rector\CodeQuality\Rector\FunctionLike\SimplifyUselessLastVariableAssignRector::class,
        \Rector\CodeQuality\Rector\FunctionLike\SimplifyUselessLastVariableAssignRector::class,
        \Rector\CodeQuality\Rector\PropertyFetch\ExplicitMethodCallOverMagicGetSetRector::class,
        \Rector\CodeQuality\Rector\Identical\BooleanNotIdenticalToNotIdenticalRector::class,
        \Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector::class,
        \Rector\CodeQuality\Rector\Identical\GetClassToInstanceOfRector::class,
        \Rector\CodeQuality\Rector\Identical\SimplifyConditionsRector::class,
        \Rector\CodeQuality\Rector\Identical\SimplifyArraySearchRector::class,
        \Rector\CodeQuality\Rector\Identical\SimplifyBoolIdenticalTrueRector::class,
        \Rector\CodeQuality\Rector\Identical\StrlenZeroToIdenticalEmptyStringRector::class,
        \Rector\CodeQuality\Rector\Equal\UseIdenticalOverEqualWithSameTypeRector::class,
        \Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector::class,
        \Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector::class,
        \Rector\CodeQuality\Rector\BooleanNot\ReplaceMultipleBooleanNotRector::class,
        \Rector\CodeQuality\Rector\BooleanNot\SimplifyDeMorganBinaryRector::class,
        \Rector\CodeQuality\Rector\BooleanAnd\SimplifyEmptyArrayCheckRector::class,
        \Rector\CodeQuality\Rector\Foreach_\ForeachToInArrayRector::class,
        \Rector\CodeQuality\Rector\Foreach_\SimplifyForeachToArrayFilterRector::class,
        \Rector\CodeQuality\Rector\Foreach_\SimplifyForeachToCoalescingRector::class,
        \Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector::class,
        \Rector\CodeQuality\Rector\If_\CombineIfRector::class,
        \Rector\CodeQuality\Rector\If_\ConsecutiveNullCompareReturnsToNullCoalesceQueueRector::class,
        \Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector::class,
        \Rector\CodeQuality\Rector\If_\ShortenElseIfRector::class,
        \Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector::class,
        \Rector\CodeQuality\Rector\If_\SimplifyIfExactValueReturnValueRector::class,
        \Rector\CodeQuality\Rector\If_\SimplifyIfNotNullReturnRector::class,
        \Rector\CodeQuality\Rector\If_\SimplifyIfNullableReturnRector::class,
        \Rector\CodeQuality\Rector\If_\SimplifyIfReturnBoolRector::class,
        \Rector\CodeQuality\Rector\Ternary\SimplifyTautologyTernaryRector::class,
        \Rector\CodeQuality\Rector\Ternary\ArrayKeyExistsTernaryThenValueToCoalescingRector::class,
        \Rector\CodeQuality\Rector\Ternary\TernaryEmptyArrayArrayDimFetchToCoalesceRector::class,
        \Rector\CodeQuality\Rector\Ternary\SwitchNegatedTernaryRector::class,
        \Rector\CodeQuality\Rector\Ternary\UnnecessaryTernaryExpressionRector::class,
        \Rector\CodeQuality\Rector\Switch_\SingularSwitchToIfRector::class,
        \Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector::class,
        \Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector::class,
        \Rector\CodeQuality\Rector\Assign\CombinedAssignRector::class,

        \Rector\CodingStyle\Rector\If_\NullableCompareToNullRector::class,
        //\Rector\CodingStyle\Rector\MethodCall\PreferThisOrSelfMethodCallRector::class,
        \Rector\CodingStyle\Rector\Ternary\TernaryConditionVariableAssignmentRector::class,
        \Rector\CodingStyle\Rector\String_\SymplifyQuoteEscapeRector::class,
        \Rector\CodingStyle\Rector\Plus\UseIncrementAssignRector::class,
        \Rector\CodingStyle\Rector\ClassMethod\DataProviderArrayItemsNewlinedRector::class,
        \Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector::class,

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

        \Rector\EarlyReturn\Rector\If_\ChangeIfElseValueAssignToEarlyReturnRector::class,
        \Rector\EarlyReturn\Rector\If_\ChangeAndIfToEarlyReturnRector::class,

        \Rector\Php53\Rector\Ternary\TernaryToElvisRector::class,
        \Rector\Php54\Rector\Array_\LongArrayToShortArrayRector::class,
        \Rector\Php70\Rector\FuncCall\RandomFunctionRector::class,
        \Rector\Php70\Rector\Ternary\TernaryToNullCoalescingRector::class,
        \Rector\Php71\Rector\FuncCall\CountOnNullRector::class,
        \Rector\Php71\Rector\TryCatch\MultiExceptionCatchRector::class,
        \Rector\Php73\Rector\FuncCall\ArrayKeyFirstLastRector::class,
        //\Rector\Privatization\Rector\Class_\FinalizeClassesWithoutChildrenRector::class,
        \Rector\TypeDeclaration\Rector\ClassMethod\ArrayShapeFromConstantArrayReturnRector::class,

        \Rector\Php74\Rector\FuncCall\ArraySpreadInsteadOfArrayMergeRector::class,
    ]);

    // define sets of rules
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_72,
    ]);

    $rectorConfig->ruleWithConfiguration(\Rector\CodingStyle\Rector\Property\InlineSimplePropertyAnnotationRector::class, [
        'var',
        'phpstan-var',
        'property',
    ]);
};
