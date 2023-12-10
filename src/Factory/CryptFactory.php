<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Crypt\AkOdePosCrypt;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Crypt\EstPosCrypt;
use Mews\Pos\Crypt\EstV3PosCrypt;
use Mews\Pos\Crypt\GarantiPosCrypt;
use Mews\Pos\Crypt\InterPosCrypt;
use Mews\Pos\Crypt\KuveytPosCrypt;
use Mews\Pos\Crypt\NullCrypt;
use Mews\Pos\Crypt\PayFlexCPV4Crypt;
use Mews\Pos\Crypt\PayForPosCrypt;
use Mews\Pos\Crypt\PosNetCrypt;
use Mews\Pos\Crypt\PosNetV1PosCrypt;
use Mews\Pos\Gateways\AkOdePos;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\PosNetV1Pos;
use Psr\Log\LoggerInterface;

/**
 * CryptFactory
 */
class CryptFactory
{
    /**
     * @param class-string    $gatewayClass
     * @param LoggerInterface $logger
     *
     * @return CryptInterface
     */
    public static function createGatewayCrypt(string $gatewayClass, LoggerInterface $logger): CryptInterface
    {
        $classMappings = [
            AkOdePos::class       => AkOdePosCrypt::class,
            EstV3Pos::class       => EstV3PosCrypt::class,
            EstPos::class         => EstPosCrypt::class,
            GarantiPos::class     => GarantiPosCrypt::class,
            InterPos::class       => InterPosCrypt::class,
            KuveytPos::class      => KuveytPosCrypt::class,
            PayForPos::class      => PayForPosCrypt::class,
            PosNet::class         => PosNetCrypt::class,
            PosNetV1Pos::class    => PosNetV1PosCrypt::class,
            PayFlexCPV4Pos::class => PayFlexCPV4Crypt::class,
        ];

        if (isset($classMappings[$gatewayClass])) {
            return new $classMappings[$gatewayClass]($logger);
        }

        return new NullCrypt();
    }
}
