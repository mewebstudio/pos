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
use Mews\Pos\Client\KuveytSoapApiPosHttpClient;
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
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class PosHttpClientFactory
{
    /**
     * @var array<class-string<HttpClientInterface>>
     */
    private static array $clientClasses = [
        AkbankPosHttpClient::class,
        EstPosHttpClient::class,
        GarantiPosHttpClient::class,
        InterPosHttpClient::class,
        KuveytPosHttpClient::class,
        KuveytSoapApiPosHttpClient::class,
        ParamPosHttpClient::class,
        PayFlexCPV4PosHttpClient::class,
        PayFlexV4PosHttpClient::class,
        PayForPosHttpClient::class,
        PosNetPosHttpClient::class,
        PosNetV1PosHttpClient::class,
        ToslaPosHttpClient::class,
        VakifKatilimPosHttpClient::class,
    ];

    /**
     * @param class-string<PosInterface>   $gatewayClass
     * @param array{
     *      payment_api: non-empty-string,
     *      payment_api2?: non-empty-string,
     *      query_api?: non-empty-string}  $gatewayEndpoints
     * @param SerializerInterface          $serializer
     * @param CryptInterface               $crypt
     * @param RequestValueMapperInterface  $requestValueMapper
     * @param LoggerInterface              $logger
     * @param ClientInterface|null         $psr18client
     * @param RequestFactoryInterface|null $requestFactory
     * @param StreamFactoryInterface|null  $streamFactory
     *
     * @return HttpClientInterface
     */
    public static function createForGateway(
        string                      $gatewayClass,
        array                       $gatewayEndpoints,
        SerializerInterface         $serializer,
        CryptInterface              $crypt,
        RequestValueMapperInterface $requestValueMapper,
        LoggerInterface             $logger,
        ?ClientInterface            $psr18client = null,
        ?RequestFactoryInterface    $requestFactory = null,
        ?StreamFactoryInterface     $streamFactory = null
    ): HttpClientInterface {

        $psr18client    ??= Psr18ClientDiscovery::find();
        $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory  ??= Psr17FactoryDiscovery::findStreamFactory();


        /** @var class-string<HttpClientInterface> $clientClass */
        foreach (self::$clientClasses as $clientClass) {
            if (!$clientClass::supports($gatewayClass)) {
                continue;
            }

            if (AkbankPosHttpClient::class === $clientClass) {
                return new $clientClass(
                    $psr18client,
                    $requestFactory,
                    $streamFactory,
                    $serializer,
                    $logger,
                    $gatewayEndpoints,
                    $crypt
                );
            }
            if (PosNetV1PosHttpClient::class === $clientClass || KuveytSoapApiPosHttpClient::class === $clientClass) {
                return new $clientClass(
                    $psr18client,
                    $requestFactory,
                    $streamFactory,
                    $serializer,
                    $logger,
                    $gatewayEndpoints,
                    $requestValueMapper,
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
