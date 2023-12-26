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
     * @param HttpClient|null          $client
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
        ?HttpClient              $client = null,
        ?LoggerInterface         $logger = null
    ): PosInterface
    {
        if (!$logger instanceof \Psr\Log\LoggerInterface) {
            $logger = new NullLogger();
        }

        if (!$client instanceof \Mews\Pos\Client\HttpClient) {
            $client = HttpClientFactory::createDefaultHttpClient();
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

        $currencies = [];
        if (isset($config['currencies'])) {
            $currencies = $config['currencies'];
        }

        $logger->debug('creating gateway for bank', ['bank' => $posAccount->getBank()]);

        $crypt              = CryptFactory::createGatewayCrypt($class, $logger);
        $requestDataMapper  = RequestDataMapperFactory::createGatewayRequestMapper($class, $eventDispatcher, $crypt, $currencies);
        $responseDataMapper = ResponseDataMapperFactory::createGatewayResponseMapper($class, $requestDataMapper, $logger);
        $serializer         = SerializerFactory::createGatewaySerializer($class);

        // Create Bank Class Instance
        return new $class(
            $config['banks'][$posAccount->getBank()],
            $posAccount,
            $requestDataMapper,
            $responseDataMapper,
            $serializer,
            $eventDispatcher,
            $client,
            $logger
        );
    }
}
