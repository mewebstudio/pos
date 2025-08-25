<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Client\KuveytSoapApiPosSoapClient;
use Mews\Pos\Client\SoapClientInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;

class PosSoapClientFactory
{
    /**
     * @param class-string<PosInterface>  $gatewayClass
     * @param array{
     *     payment_api: non-empty-string,
     *     payment_api2?: non-empty-string,
     *     query_api?: non-empty-string}  $gatewayEndpoints
     * @param RequestValueMapperInterface $requestValueMapper
     * @param LoggerInterface             $logger
     *
     * @return SoapClientInterface
     */
    public static function createForGateway(
        string                      $gatewayClass,
        array                       $gatewayEndpoints,
        RequestValueMapperInterface $requestValueMapper,
        LoggerInterface             $logger
    ): SoapClientInterface {
        $clients = [
            KuveytSoapApiPosSoapClient::class,
        ];

        /** @var class-string<SoapClientInterface> $clientClass */
        foreach ($clients as $clientClass) {
            if (!$clientClass::supports($gatewayClass)) {
                continue;
            }

            return new $clientClass(
                $gatewayEndpoints,
                $requestValueMapper,
                $logger
            );
        }

        throw new \DomainException(\sprintf('Client not found for the gateway %s', $gatewayClass));
    }
}
