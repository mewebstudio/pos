<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Mews\Pos\Client\AkbankPosHttpClient;
use Mews\Pos\Client\EstPosHttpClient;
use Mews\Pos\Client\GarantiPosHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\InterPosHttpClient;
use Mews\Pos\Client\KuveytPosHttpClient;
use Mews\Pos\Client\ParamPosHttpClient;
use Mews\Pos\Client\PayFlexCPV4PosHttpClient;
use Mews\Pos\Client\PayFlexV4PosHttpClient;
use Mews\Pos\Client\PayForPosHttpClient;
use Mews\Pos\Client\PosNetPosHttpClient;
use Mews\Pos\Client\PosNetV1PosHttpClient;
use Mews\Pos\Client\ToslaPosHttpClient;
use Mews\Pos\Client\VakifKatilimPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class ClientFactory
{
    public static function createForGateway(
        string                      $gatewayClass,
        array                       $gatewayEndpoints,
        SerializerInterface         $serializer,
        CryptInterface              $crypt,
        RequestValueMapperInterface $requestValueMapper,
        LoggerInterface             $logger,
        ClientInterface             $psr18client = null,
        RequestFactoryInterface     $requestFactory = null,
        StreamFactoryInterface      $streamFactory = null
    ): HttpClientInterface
    {
        $clients = [
            AkbankPosHttpClient::class,
            EstPosHttpClient::class,
            GarantiPosHttpClient::class,
            InterPosHttpClient::class,
            KuveytPosHttpClient::class,
            ParamPosHttpClient::class,
            PayFlexCPV4PosHttpClient::class,
            PayFlexV4PosHttpClient::class,
            PayForPosHttpClient::class,
            PosNetPosHttpClient::class,
            PosNetV1PosHttpClient::class,
            ToslaPosHttpClient::class,
            VakifKatilimPosHttpClient::class,
        ];

        // todo create here?
        $psr18client    ??= Psr18ClientDiscovery::find();
        $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory  ??= Psr17FactoryDiscovery::findStreamFactory();


        /** @var class-string<HttpClientInterface> $clientClass */
        foreach ($clients as $clientClass) {
            if (!$clientClass::supports($gatewayClass)) {
                continue;
            }

            // todo
            if (AkbankPosHttpClient::class === $clientClass) {
                return new AkbankPosHttpClient(
                    $crypt,
                    $psr18client,
                    $requestFactory,
                    $streamFactory,
                    $serializer,
                    $logger,
                    $gatewayEndpoints
                );
            }
            if (PosNetV1PosHttpClient::class === $clientClass) {
                return new PosNetV1PosHttpClient(
                    $psr18client,
                    $requestFactory,
                    $streamFactory,
                    $serializer,
                    $requestValueMapper,
                    $logger,
                    $gatewayEndpoints
                );
            }

            return new $clientClass(
                $psr18client,
                $requestFactory,
                $streamFactory,
                $serializer,
                $logger,
                $gatewayEndpoints
            );
        }

        throw new \DomainException(\sprintf('Client not found for the gateway %s', $gatewayClass));
    }
}
