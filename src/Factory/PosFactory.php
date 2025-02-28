<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Client\HttpClient;
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
     * @phpstan-param array{banks: array<string, array{name: string, class?: class-string, gateway_endpoints: array<string, string>}>, currencies?: array<PosInterface::CURRENCY_*, string>} $config
     *
     * @param AbstractPosAccount       $posAccount
     * @param array                    $config
     * @param EventDispatcherInterface $eventDispatcher
     * @param HttpClient|null          $httpClient
     * @param LoggerInterface|null     $logger
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
        ?HttpClient              $httpClient = null,
        ?LoggerInterface         $logger = null
    ): PosInterface {
        if (!$logger instanceof \Psr\Log\LoggerInterface) {
            $logger = new NullLogger();
        }

        if (!$httpClient instanceof \Mews\Pos\Client\HttpClient) {
            $httpClient = HttpClientFactory::createDefaultHttpClient();
        }

        // Bank API Exist
        if (!\array_key_exists($posAccount->getBank(), $config['banks'])) {
            throw new BankNotFoundException();
        }

        /** @var class-string|null $class Gateway Class */
        $class = $config['banks'][$posAccount->getBank()]['class'] ?? null;

        if (null === $class) {
            throw new BankClassNullException();
        }

        if (!\in_array(PosInterface::class, \class_implements($class), true)) {
            throw new \InvalidArgumentException(
                \sprintf('gateway class must be implementation of %s', PosInterface::class)
            );
        }

        $logger->debug('creating gateway for bank', ['bank' => $posAccount->getBank()]);

        $crypt                 = CryptFactory::createGatewayCrypt($class, $logger);
        $requestValueMapper    = RequestValueMapperFactory::createForGateway($class);
        $requestValueFormatter = RequestValueFormatterFactory::createForGateway($class);
        $requestDataMapper     = RequestDataMapperFactory::createGatewayRequestMapper(
            $class,
            $requestValueMapper,
            $requestValueFormatter,
            $eventDispatcher,
            $crypt,
        );

        $responseValueFormatter = ResponseValueFormatterFactory::createForGateway($class);
        $responseValueMapper    = ResponseValueMapperFactory::createForGateway($class, $requestValueMapper);
        $responseDataMapper     = ResponseDataMapperFactory::createGatewayResponseMapper($class, $responseValueFormatter, $responseValueMapper, $logger);
        $serializer             = SerializerFactory::createGatewaySerializer($class);
        $client = ClientFactory::createForGateway(
            $class,
            $config['banks'][$posAccount->getBank()]['gateway_endpoints'],
            $serializer,
            $crypt,
            $requestValueMapper,
            $logger
        );

        // Create Bank Class Instance
        return new $class(
            $config['banks'][$posAccount->getBank()],
            $posAccount,
            $requestValueMapper,
            $requestDataMapper,
            $responseDataMapper,
            $serializer,
            $eventDispatcher,
            $httpClient,
            $client,
            $logger
        );
    }
}
