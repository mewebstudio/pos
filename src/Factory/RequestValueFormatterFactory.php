<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\DataMapper\RequestValueFormatter\AkbankPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\EstPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\GarantiPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\InterPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\KuveytPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\ParamPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PayFlexCPV4PosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PayFlexV4PosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PayForPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PosNetRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\PosNetV1PosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\RequestValueFormatter\ToslaPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\VakifKatilimPosRequestValueFormatter;
use Mews\Pos\PosInterface;

/**
 * RequestValueFormatterFactory
 */
class RequestValueFormatterFactory
{
    /**
     * @var class-string<RequestValueFormatterInterface>[]
     */
    private static array $requestValueFormatterClasses = [
        ToslaPosRequestValueFormatter::class,
        AkbankPosRequestValueFormatter::class,
        EstPosRequestValueFormatter::class,
        GarantiPosRequestValueFormatter::class,
        InterPosRequestValueFormatter::class,
        KuveytPosRequestValueFormatter::class,
        VakifKatilimPosRequestValueFormatter::class,
        ParamPosRequestValueFormatter::class,
        PayForPosRequestValueFormatter::class,
        PosNetRequestValueFormatter::class,
        PosNetV1PosRequestValueFormatter::class,
        PayFlexCPV4PosRequestValueFormatter::class,
        PayFlexV4PosRequestValueFormatter::class,
    ];

    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return RequestValueFormatterInterface
     */
    public static function createForGateway(string $gatewayClass): RequestValueFormatterInterface
    {
        /** @var class-string<RequestValueFormatterInterface> $valueFormatterClass */
        foreach (self::$requestValueFormatterClasses as $valueFormatterClass) {
            if ($valueFormatterClass::supports($gatewayClass)) {
                return new $valueFormatterClass();
            }
        }

        throw new DomainException('unsupported gateway');
    }
}
