<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Client\AkbankPosHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\KuveytSoapApiPosHttpClient;
use Mews\Pos\Client\PosNetV1PosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class PosHttpClientFactory
{
    /**
     * @param class-string<HttpClientInterface> $clientClass
     * @param non-empty-string                  $baseApiUrl
     * @param SerializerInterface               $serializer
     * @param CryptInterface                    $crypt
     * @param RequestValueMapperInterface       $requestValueMapper
     * @param LoggerInterface                   $logger
     * @param ClientInterface                   $psr18client
     * @param RequestFactoryInterface           $requestFactory
     * @param StreamFactoryInterface            $streamFactory
     *
     * @return HttpClientInterface
     */
    public static function create(
        string                      $clientClass,
        string                      $baseApiUrl,
        SerializerInterface         $serializer,
        CryptInterface              $crypt,
        RequestValueMapperInterface $requestValueMapper,
        LoggerInterface             $logger,
        ClientInterface             $psr18client,
        RequestFactoryInterface     $requestFactory,
        StreamFactoryInterface      $streamFactory
    ): HttpClientInterface {
        if (AkbankPosHttpClient::class === $clientClass) {
            return new $clientClass(
                $baseApiUrl,
                $psr18client,
                $requestFactory,
                $streamFactory,
                $serializer,
                $logger,
                $crypt
            );
        }
        if (PosNetV1PosHttpClient::class === $clientClass || KuveytSoapApiPosHttpClient::class === $clientClass) {
            return new $clientClass(
                $baseApiUrl,
                $psr18client,
                $requestFactory,
                $streamFactory,
                $serializer,
                $logger,
                $requestValueMapper
            );
        }

        return new $clientClass(
            $baseApiUrl,
            $psr18client,
            $requestFactory,
            $streamFactory,
            $serializer,
            $logger
        );
    }
}
