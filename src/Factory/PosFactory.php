<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\Client\HttpClient;
use Mews\Pos\DataMapper\AbstractRequestDataMapper;
use Mews\Pos\DataMapper\EstPosRequestDataMapper;
use Mews\Pos\DataMapper\EstV3PosRequestDataMapper;
use Mews\Pos\DataMapper\GarantiPosRequestDataMapper;
use Mews\Pos\DataMapper\InterPosRequestDataMapper;
use Mews\Pos\DataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\PayForPosRequestDataMapper;
use Mews\Pos\DataMapper\PosNetRequestDataMapper;
use Mews\Pos\DataMapper\VakifBankPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\VakifBankPos;
use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PosFactory
 */
class PosFactory
{
    /**
     * @param AbstractPosAccount   $posAccount
     * @param array|string|null    $config config path or config array
     * @param HttpClient|null $client
     * @param LoggerInterface|null $logger
     *
     * @return PosInterface
     *
     * @throws BankClassNullException
     * @throws BankNotFoundException
     */
    public static function createPosGateway(
        AbstractPosAccount $posAccount,
        $config = null,
        ?HttpClient $client = null,
        ?LoggerInterface $logger = null
    ): PosInterface {
        if (!$logger) {
            $logger = new NullLogger();
        }
        if (!$client) {
            $client = HttpClientFactory::createDefaultHttpClient();
        }
        if (is_string($config)) {
            $config = require $config;
        } elseif (empty($config)) {
            $config = require __DIR__.'/../../config/pos.php';
        }

        // Bank API Exist
        if (!array_key_exists($posAccount->getBank(), $config['banks'])) {
            throw new BankNotFoundException();
        }

        // Gateway Class
        $class = $config['banks'][$posAccount->getBank()]['class'];

        if (!$class) {
            throw new BankClassNullException();
        }

        $currencies = [];
        if (isset($config['currencies'])) {
            $currencies = $config['currencies'];
        }
        $logger->debug('creating gateway for bank', ['bank' => $posAccount->getBank()]);
        // Create Bank Class Object
        return new $class(
            $config['banks'][$posAccount->getBank()],
            $posAccount,
            self::getGatewayMapper($class, $currencies),
            $client,
            $logger
        );
    }

    /**
     * @param string $gatewayClass
     * @param array  $currencies
     *
     * @return AbstractRequestDataMapper
     */
    public static function getGatewayMapper(string $gatewayClass, array $currencies = []): AbstractRequestDataMapper
    {
        switch ($gatewayClass) {
            case EstPos::class:
                return new EstPosRequestDataMapper($currencies);
            case EstV3Pos::class:
                return new EstV3PosRequestDataMapper($currencies);
            case GarantiPos::class:
                return new GarantiPosRequestDataMapper($currencies);
            case InterPos::class:
                return new InterPosRequestDataMapper($currencies);
            case KuveytPos::class:
                return new KuveytPosRequestDataMapper($currencies);
            case PayForPos::class:
                return new PayForPosRequestDataMapper($currencies);
            case PosNet::class:
                return new PosNetRequestDataMapper($currencies);
            case VakifBankPos::class:
                return new VakifBankPosRequestDataMapper($currencies);
        }
        throw new DomainException('unsupported gateway');
    }
}
