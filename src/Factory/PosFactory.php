<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Factory;

use DomainException;
use Mews\Pos\Client\HttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Crypt\EstPosCrypt;
use Mews\Pos\Crypt\EstV3PosCrypt;
use Mews\Pos\Crypt\GarantiPosCrypt;
use Mews\Pos\Crypt\InterPosCrypt;
use Mews\Pos\Crypt\KuveytPosCrypt;
use Mews\Pos\Crypt\PayForPosCrypt;
use Mews\Pos\Crypt\PosNetCrypt;
use Mews\Pos\DataMapper\AbstractRequestDataMapper;
use Mews\Pos\DataMapper\EstPosRequestDataMapper;
use Mews\Pos\DataMapper\EstV3PosRequestDataMapper;
use Mews\Pos\DataMapper\GarantiPosRequestDataMapper;
use Mews\Pos\DataMapper\InterPosRequestDataMapper;
use Mews\Pos\DataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\PayForPosRequestDataMapper;
use Mews\Pos\DataMapper\PosNetRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\EstPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\GarantiPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\InterPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayForPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\VakifBankPosResponseDataMapper;
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
     * @param HttpClient|null      $client
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
        ?HttpClient        $client = null,
        ?LoggerInterface   $logger = null
    ): PosInterface
    {
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

        /** @var class-string $class Gateway Class*/
        $class = $config['banks'][$posAccount->getBank()]['class'];

        if (!$class) {
            throw new BankClassNullException();
        }

        $currencies = [];
        if (isset($config['currencies'])) {
            $currencies = $config['currencies'];
        }
        $logger->debug('creating gateway for bank', ['bank' => $posAccount->getBank()]);

        $crypt              = self::getGatewayCrypt($class, $logger);
        $requestDataMapper  = self::getGatewayRequestMapper($class, $currencies, $crypt);
        $responseDataMapper = self::getGatewayResponseMapper($class, $requestDataMapper, $logger);

        // Create Bank Class Instance
        return new $class(
            $config['banks'][$posAccount->getBank()],
            $posAccount,
            $requestDataMapper,
            $responseDataMapper,
            $client,
            $logger
        );
    }

    /**
     * @param class-string        $gatewayClass
     * @param array               $currencies
     * @param CryptInterface|null $crypt
     *
     * @return AbstractRequestDataMapper
     */
    public static function getGatewayRequestMapper(string $gatewayClass, array $currencies = [], ?CryptInterface $crypt = null): AbstractRequestDataMapper
    {
        if (null !== $crypt) {
            switch ($gatewayClass) {
                case EstPos::class:
                    return new EstPosRequestDataMapper($crypt, $currencies);
                case EstV3Pos::class:
                    return new EstV3PosRequestDataMapper($crypt, $currencies);
                case GarantiPos::class:
                    return new GarantiPosRequestDataMapper($crypt, $currencies);
                case InterPos::class:
                    return new InterPosRequestDataMapper($crypt, $currencies);
                case KuveytPos::class:
                    return new KuveytPosRequestDataMapper($crypt, $currencies);
                case PayForPos::class:
                    return new PayForPosRequestDataMapper($crypt, $currencies);
                case PosNet::class:
                    return new PosNetRequestDataMapper($crypt, $currencies);
            }
        }
        switch ($gatewayClass) {
            case VakifBankPos::class:
                return new VakifBankPosRequestDataMapper(null, $currencies);
        }
        throw new DomainException('unsupported gateway');
    }

    /**
     * @param class-string              $gatewayClass
     * @param AbstractRequestDataMapper $requestDataMapper
     * @param LoggerInterface           $logger
     *
     * @return AbstractResponseDataMapper
     */
    public static function getGatewayResponseMapper(string $gatewayClass, AbstractRequestDataMapper $requestDataMapper, LoggerInterface $logger): AbstractResponseDataMapper
    {
        $currencyMappings = $requestDataMapper->getCurrencyMappings();
        $txMappings       = $requestDataMapper->getTxTypeMappings();
        switch ($gatewayClass) {
            case EstV3Pos::class:
            case EstPos::class:
                return new EstPosResponseDataMapper($currencyMappings, $txMappings, $logger);
            case GarantiPos::class:
                return new GarantiPosResponseDataMapper($currencyMappings, $txMappings, $logger);
            case InterPos::class:
                return new InterPosResponseDataMapper($currencyMappings, $txMappings, $logger);
            case KuveytPos::class:
                return new KuveytPosResponseDataMapper($currencyMappings, $txMappings, $logger);
            case PayForPos::class:
                return new PayForPosResponseDataMapper($currencyMappings, $txMappings, $logger);
            case PosNet::class:
                return new PosNetResponseDataMapper($currencyMappings, $txMappings, $logger);
            case VakifBankPos::class:
                return new VakifBankPosResponseDataMapper($currencyMappings, $txMappings, $logger);
        }
        throw new DomainException('unsupported gateway');
    }

    /**
     * @param class-string    $gatewayClass
     * @param LoggerInterface $logger
     *
     * @return CryptInterface|null
     */
    public static function getGatewayCrypt(string $gatewayClass, LoggerInterface $logger): ?CryptInterface
    {
        switch ($gatewayClass) {
            case EstV3Pos::class:
                return new EstV3PosCrypt($logger);
            case EstPos::class:
                return new EstPosCrypt($logger);
            case GarantiPos::class:
                return new GarantiPosCrypt($logger);
            case InterPos::class:
                return new InterPosCrypt($logger);
            case KuveytPos::class:
                return new KuveytPosCrypt($logger);
            case PayForPos::class:
                return new PayForPosCrypt($logger);
            case PosNet::class:
                return new PosNetCrypt($logger);
            default:
                return null;
        }
    }
}
