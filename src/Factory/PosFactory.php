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
use Mews\Pos\Crypt\PayFlexCPV4Crypt;
use Mews\Pos\DataMapper\AbstractRequestDataMapper;
use Mews\Pos\DataMapper\EstPosRequestDataMapper;
use Mews\Pos\DataMapper\EstV3PosRequestDataMapper;
use Mews\Pos\DataMapper\GarantiPosRequestDataMapper;
use Mews\Pos\DataMapper\InterPosRequestDataMapper;
use Mews\Pos\DataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\DataMapper\PayFlexV4PosRequestDataMapper;
use Mews\Pos\DataMapper\PayForPosRequestDataMapper;
use Mews\Pos\DataMapper\PosNetRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\EstPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\GarantiPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\InterPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexCPV4PosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexV4PosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayForPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\VakifBankCPPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\VakifBankPosResponseDataMapper;
use Mews\Pos\DataMapper\VakifBankCPPosRequestDataMapper;
use Mews\Pos\DataMapper\VakifBankPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\VakifBankCPPos;
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
        if ($logger === null) {
            $logger = new NullLogger();
        }

        if ($client === null) {
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
     * @param class-string          $gatewayClass
     * @param array<string, string> $currencies
     * @param CryptInterface|null   $crypt
     *
     * @return AbstractRequestDataMapper
     */
    public static function getGatewayRequestMapper(string $gatewayClass, array $currencies = [], ?CryptInterface $crypt = null): AbstractRequestDataMapper
    {
        $classMappings = [
            EstPos::class         => EstPosRequestDataMapper::class,
            EstV3Pos::class       => EstV3PosRequestDataMapper::class,
            GarantiPos::class     => GarantiPosRequestDataMapper::class,
            InterPos::class       => InterPosRequestDataMapper::class,
            KuveytPos::class      => KuveytPosRequestDataMapper::class,
            PayForPos::class      => PayForPosRequestDataMapper::class,
            PosNet::class         => PosNetRequestDataMapper::class,
            PayFlexCPV4Pos::class => PayFlexCPV4PosRequestDataMapper::class,
            VakifBankCPPos::class => VakifBankCPPosRequestDataMapper::class,
        ];
        if (isset($classMappings[$gatewayClass])) {
            if (null === $crypt) {
                throw new \InvalidArgumentException(sprintf('Gateway %s requires Crypt instance', $gatewayClass));
            }

            return new $classMappings[$gatewayClass]($crypt, $currencies);
        }

        if ($gatewayClass === VakifBankPos::class) {
            return new VakifBankPosRequestDataMapper(null, $currencies);
        }

        if ($gatewayClass === PayFlexV4Pos::class) {
            return new PayFlexV4PosRequestDataMapper(null, $currencies);
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
        $classMappings = [
            EstV3Pos::class       => EstPosResponseDataMapper::class,
            EstPos::class         => EstPosResponseDataMapper::class,
            GarantiPos::class     => GarantiPosResponseDataMapper::class,
            InterPos::class       => InterPosResponseDataMapper::class,
            KuveytPos::class      => KuveytPosResponseDataMapper::class,
            PayForPos::class      => PayForPosResponseDataMapper::class,
            PosNet::class         => PosNetResponseDataMapper::class,
            PayFlexV4Pos::class   => PayFlexV4PosResponseDataMapper::class,
            VakifBankPos::class   => VakifBankPosResponseDataMapper::class,
            VakifBankCPPos::class => VakifBankCPPosResponseDataMapper::class,
            PayFlexCPV4Pos::class => PayFlexCPV4PosResponseDataMapper::class,
        ];

        if (isset($classMappings[$gatewayClass])) {
            return new $classMappings[$gatewayClass](
                $requestDataMapper->getCurrencyMappings(),
                $requestDataMapper->getTxTypeMappings(),
                $logger
            );
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
        $classMappings = [
            EstV3Pos::class       => EstV3PosCrypt::class,
            EstPos::class         => EstPosCrypt::class,
            GarantiPos::class     => GarantiPosCrypt::class,
            InterPos::class       => InterPosCrypt::class,
            KuveytPos::class      => KuveytPosCrypt::class,
            PayForPos::class      => PayForPosCrypt::class,
            PosNet::class         => PosNetCrypt::class,
            VakifBankCPPos::class => PayFlexCPV4Crypt::class,
            PayFlexCPV4Pos::class => PayFlexCPV4Crypt::class,
        ];

        if (isset($classMappings[$gatewayClass])) {
            return new $classMappings[$gatewayClass]($logger);
        }

        return null;
    }
}
