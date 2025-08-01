<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\SoapClient;
use Mews\Pos\Client\SoapClientInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Gateways\AbstractHttpGateway;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PosFactory
 */
class PosFactory
{
    /**
     * @phpstan-param array{banks: array<string, array{name: string, class?: class-string<PosInterface>, gateway_endpoints: array<string, string>}>, currencies?: array<PosInterface::CURRENCY_*, string>} $config
     *
     * @param AbstractPosAccount                           $posAccount
     * @param array                                        $config
     * @param EventDispatcherInterface                     $eventDispatcher
     * @param HttpClientInterface|SoapClientInterface|null $client
     * @param LoggerInterface|null                         $logger
     *
     * @return PosInterface
     *
     * @throws BankClassNullException
     * @throws BankNotFoundException
     */
    public static function createPosGateway(
        AbstractPosAccount       $posAccount,
        array                    $config,
        EventDispatcherInterface $eventDispatcher,
        $client = null,
        ?LoggerInterface         $logger = null
    ): PosInterface {
        if (!$logger instanceof \Psr\Log\LoggerInterface) {
            $logger = new NullLogger();
        }

        // Bank Config Exist
        if (!\array_key_exists($posAccount->getBank(), $config['banks'])) {
            throw new BankNotFoundException();
        }

        $gatewayClass = $config['banks'][$posAccount->getBank()]['class'] ?? null;

        if (null === $gatewayClass) {
            throw new BankClassNullException();
        }

        if (!\in_array(PosInterface::class, \class_implements($gatewayClass), true)) {
            throw new \InvalidArgumentException(
                \sprintf('gateway class must be implementation of %s', PosInterface::class)
            );
        }

        $logger->debug('creating gateway for bank', ['bank' => $posAccount->getBank()]);

        if (\in_array(AbstractHttpGateway::class, \class_parents($gatewayClass), true)) {
            return self::doCreateHttpPosGateway(
                $gatewayClass,
                $posAccount,
                $config['banks'][$posAccount->getBank()],
                $eventDispatcher,
                $logger,
                $client
            );
        }

        return self::doCreateSoapPosGateway(
            $gatewayClass,
            $posAccount,
            $config['banks'][$posAccount->getBank()],
            $eventDispatcher,
            $logger,
            $client
        );
    }

    /**
     * @param class-string<PosInterface>                                                          $gatewayClass
     * @param AbstractPosAccount                                                                  $posAccount
     * @param array{name: string, class?: class-string, gateway_endpoints: array<string, string>} $apiConfig
     * @param EventDispatcherInterface                                                            $eventDispatcher
     * @param LoggerInterface                                                                     $logger
     * @param SoapClientInterface|null                                                            $soapClient
     *
     * @return PosInterface
     */
    private static function doCreateSoapPosGateway(
        string                   $gatewayClass,
        AbstractPosAccount       $posAccount,
        array                    $apiConfig,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface          $logger,
        ?SoapClientInterface     $soapClient = null
    ): PosInterface {
        if (!$soapClient instanceof \Mews\Pos\Client\SoapClientInterface) {
            $soapClient = new SoapClient($logger);
        }

        $logger->debug('creating gateway for bank', ['bank' => $posAccount->getBank()]);

        $crypt                 = CryptFactory::createGatewayCrypt($gatewayClass, $logger);
        $requestValueMapper    = RequestValueMapperFactory::createForGateway($gatewayClass);
        $requestValueFormatter = RequestValueFormatterFactory::createForGateway($gatewayClass);
        $requestDataMapper     = RequestDataMapperFactory::createGatewayRequestMapper(
            $gatewayClass,
            $requestValueMapper,
            $requestValueFormatter,
            $eventDispatcher,
            $crypt,
        );

        $responseValueFormatter = ResponseValueFormatterFactory::createForGateway($gatewayClass);
        $responseValueMapper    = ResponseValueMapperFactory::createForGateway($gatewayClass, $requestValueMapper);
        $responseDataMapper     = ResponseDataMapperFactory::createGatewayResponseMapper($gatewayClass, $responseValueFormatter, $responseValueMapper, $logger);

        // Create Bank Class Instance
        return new $gatewayClass(
            $apiConfig,
            $posAccount,
            $requestValueMapper,
            $requestDataMapper,
            $responseDataMapper,
            $eventDispatcher,
            $soapClient,
            $logger
        );
    }

    /**
     * @param class-string<PosInterface>                                                          $gatewayClass
     * @param AbstractPosAccount                                                                  $posAccount
     * @param array{name: string, class?: class-string, gateway_endpoints: array<string, string>} $apiConfig
     * @param EventDispatcherInterface                                                            $eventDispatcher
     * @param LoggerInterface                                                                     $logger
     * @param HttpClientInterface|null                                                            $httpClient
     *
     * @return PosInterface
     */
    private static function doCreateHttpPosGateway(
        string                   $gatewayClass,
        AbstractPosAccount       $posAccount,
        array                    $apiConfig,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface          $logger,
        ?HttpClientInterface     $httpClient = null
    ): PosInterface {

        if (!$httpClient instanceof HttpClientInterface) {
            $httpClient = HttpClientFactory::createDefaultHttpClient();
        }

        $crypt                 = CryptFactory::createGatewayCrypt($gatewayClass, $logger);
        $requestValueMapper    = RequestValueMapperFactory::createForGateway($gatewayClass);
        $requestValueFormatter = RequestValueFormatterFactory::createForGateway($gatewayClass);
        $requestDataMapper     = RequestDataMapperFactory::createGatewayRequestMapper(
            $gatewayClass,
            $requestValueMapper,
            $requestValueFormatter,
            $eventDispatcher,
            $crypt,
        );

        $responseValueFormatter = ResponseValueFormatterFactory::createForGateway($gatewayClass);
        $responseValueMapper    = ResponseValueMapperFactory::createForGateway($gatewayClass, $requestValueMapper);
        $responseDataMapper     = ResponseDataMapperFactory::createGatewayResponseMapper($gatewayClass, $responseValueFormatter, $responseValueMapper, $logger);
        $serializer             = SerializerFactory::createGatewaySerializer($gatewayClass);

        // Create Bank Class Instance
        return new $gatewayClass(
            $apiConfig,
            $posAccount,
            $requestValueMapper,
            $requestDataMapper,
            $responseDataMapper,
            $serializer,
            $eventDispatcher,
            $httpClient,
            $logger
        );
    }
}
