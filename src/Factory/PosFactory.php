<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
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
     * @phpstan-param array{
     *     banks: array<string, array{
     *          name: string,
     *          class?: class-string<PosInterface>,
     *          gateway_endpoints: array{
     *              payment_api: non-empty-string,
     *              payment_api2?: non-empty-string,
     *              query_api?: non-empty-string}
     *         }>,
     *     currencies?: array<PosInterface::CURRENCY_*, string>} $config
     *
     * @param AbstractPosAccount                                 $posAccount
     * @param array                                              $config
     * @param EventDispatcherInterface                           $eventDispatcher
     * @param HttpClientStrategyInterface|null                   $httpClientStrategy
     * @param LoggerInterface|null                               $logger
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
        ?HttpClientStrategyInterface                         $httpClientStrategy = null,
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

        return self::doCreateHttpPosGateway(
            $gatewayClass,
            $posAccount,
            $config['banks'][$posAccount->getBank()],
            $eventDispatcher,
            $logger,
            $httpClientStrategy
        );
    }

    /**
     * @param class-string<PosInterface> $gatewayClass
     * @param AbstractPosAccount         $posAccount
     * @param array{
     *           name: string,
     *           class?: class-string,
     *           gateway_endpoints: array{
     *               payment_api: non-empty-string,
     *               payment_api2?: non-empty-string,
     *               query_api?: non-empty-string}
     *          }                      $apiConfig
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface          $logger
     * @param HttpClientStrategyInterface|null $httpClientStrategy
     *
     * @return PosInterface
     */
    private static function doCreateHttpPosGateway(
        string                   $gatewayClass,
        AbstractPosAccount       $posAccount,
        array                    $apiConfig,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface          $logger,
        ?HttpClientStrategyInterface $httpClientStrategy = null
    ): PosInterface {


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

        if (!$httpClientStrategy instanceof HttpClientStrategyInterface) {
            $httpClientStrategy = PosHttpClientStrategyFactory::createForGateway(
                $gatewayClass,
                $apiConfig['gateway_endpoints'],
                $crypt,
                $requestValueMapper,
                $logger
            );
        }

        // Create Bank Class Instance
        return new $gatewayClass(
            $apiConfig,
            $posAccount,
            $requestValueMapper,
            $requestDataMapper,
            $responseDataMapper,
            $serializer,
            $eventDispatcher,
            $httpClientStrategy,
            $logger
        );
    }
}
