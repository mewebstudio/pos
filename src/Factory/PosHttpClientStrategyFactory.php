<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Mews\Pos\Client\AkbankPosHttpClient;
use Mews\Pos\Client\GenericPosHttpClientStrategy;
use Mews\Pos\Client\EstPosHttpClient;
use Mews\Pos\Client\GarantiPosHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Client\InterPosHttpClient;
use Mews\Pos\Client\KuveytPos3DFormHttpClient;
use Mews\Pos\Client\KuveytPosHttpClient;
use Mews\Pos\Client\KuveytSoapApiPosHttpClient;
use Mews\Pos\Client\ParamPosHttpClient;
use Mews\Pos\Client\PayFlexCPV4Pos3DFormHttpClient;
use Mews\Pos\Client\PayFlexCPV4PosHttpClient;
use Mews\Pos\Client\PayFlexV4Pos3DFormHttpClient;
use Mews\Pos\Client\PayFlexV4PosHttpClient;
use Mews\Pos\Client\PayForPosHttpClient;
use Mews\Pos\Client\PosNetPosHttpClient;
use Mews\Pos\Client\PosNetV1PosHttpClient;
use Mews\Pos\Client\ToslaPosHttpClient;
use Mews\Pos\Client\VakifKatilimPos3DFormHttpClient;
use Mews\Pos\Client\VakifKatilimPosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class PosHttpClientStrategyFactory
{
    /**
     * @var array<class-string<HttpClientInterface>>
     */
    private static array $clientClasses = [
        AkbankPosHttpClient::class,
        EstPosHttpClient::class,
        GarantiPosHttpClient::class,
        InterPosHttpClient::class,
        KuveytPos3DFormHttpClient::class,
        KuveytPosHttpClient::class,
        KuveytSoapApiPosHttpClient::class,
        ParamPosHttpClient::class,
        PayFlexCPV4Pos3DFormHttpClient::class,
        PayFlexCPV4PosHttpClient::class,
        PayFlexV4Pos3DFormHttpClient::class,
        PayFlexV4PosHttpClient::class,
        PayForPosHttpClient::class,
        PosNetPosHttpClient::class,
        PosNetV1PosHttpClient::class,
        ToslaPosHttpClient::class,
        VakifKatilimPos3DFormHttpClient::class,
        VakifKatilimPosHttpClient::class,
    ];

    /**
     * @param class-string<PosInterface>                               $gatewayClass
     * @param array<HttpClientInterface::API_NAME_*, non-empty-string> $gatewayEndpoints
     * @param CryptInterface                                           $crypt
     * @param RequestValueMapperInterface                              $requestValueMapper
     * @param LoggerInterface                                          $logger
     * @param ClientInterface|null                                     $psr18client
     * @param RequestFactoryInterface|null                             $requestFactory
     * @param StreamFactoryInterface|null                              $streamFactory
     *
     * @return HttpClientStrategyInterface
     */
    public static function createForGateway(
        string                      $gatewayClass,
        array                       $gatewayEndpoints,
        CryptInterface              $crypt,
        RequestValueMapperInterface $requestValueMapper,
        LoggerInterface             $logger,
        ?ClientInterface            $psr18client = null,
        ?RequestFactoryInterface    $requestFactory = null,
        ?StreamFactoryInterface     $streamFactory = null
    ): HttpClientStrategyInterface {
        $clients        = [];
        $psr18client    ??= Psr18ClientDiscovery::find();
        $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory  ??= Psr17FactoryDiscovery::findStreamFactory();


        foreach ($gatewayEndpoints as $apiName => $endpoint) {
            foreach (self::$clientClasses as $clientClass) {
                if ($clientClass::supports($gatewayClass, $apiName)) {
                    $serializer = SerializerFactory::createGatewaySerializer($gatewayClass, $apiName);

                    $clients[$apiName] = PosHttpClientFactory::create(
                        $clientClass,
                        $endpoint,
                        $serializer,
                        $crypt,
                        $requestValueMapper,
                        $logger,
                        $psr18client,
                        $requestFactory,
                        $streamFactory
                    );
                }
            }
        }

        if ([] === $clients) {
            throw new \DomainException(\sprintf('Client not found for the gateway %s', $gatewayClass));
        }

        return new GenericPosHttpClientStrategy($clients);
    }
}
