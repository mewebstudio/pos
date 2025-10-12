<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\ResponseValueFormatter\BasicResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\BoaPosResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\EstPosResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\GarantiPosResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\InterPosResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\ParamPosResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\PosNetResponseValueFormatter;
use Mews\Pos\DataMapper\ResponseValueFormatter\ResponseValueFormatterInterface;
use Mews\Pos\DataMapper\ResponseValueFormatter\ToslaPosResponseValueFormatter;
use Mews\Pos\PosInterface;

/**
 * ResponseValueFormatterFactory
 */
class ResponseValueFormatterFactory
{
    /**
     * @var class-string<ResponseValueFormatterInterface>[]
     */
    private static array $valueFormatterClasses = [
        BasicResponseValueFormatter::class,
        EstPosResponseValueFormatter::class,
        GarantiPosResponseValueFormatter::class,
        InterPosResponseValueFormatter::class,
        BoaPosResponseValueFormatter::class,
        ParamPosResponseValueFormatter::class,
        PosNetResponseValueFormatter::class,
        ToslaPosResponseValueFormatter::class,
        BoaPosResponseValueFormatter::class,
    ];

    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return ResponseValueFormatterInterface
     */
    public static function createForGateway(string $gatewayClass): ResponseValueFormatterInterface
    {
        /** @var class-string<ResponseValueFormatterInterface> $formatterClass */
        foreach (self::$valueFormatterClasses as $formatterClass) {
            if ($formatterClass::supports($gatewayClass)) {
                return new $formatterClass();
            }
        }

        throw new DomainException('unsupported gateway');
    }
}
