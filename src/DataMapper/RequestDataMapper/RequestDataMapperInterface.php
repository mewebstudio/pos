<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;

interface RequestDataMapperInterface
{
    /**
     * @return array<PosInterface::TX_TYPE_*, string>
     */
    public function getTxTypeMappings(): array;

    /**
     * @phpstan-param PosInterface::TX_TYPE_* $txType
     *
     * @param string $txType
     *
     * @return string
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function mapTxType(string $txType): string;

    /**
     * @return non-empty-array<PosInterface::CURRENCY_*, string>
     */
    public function getCurrencyMappings(): array;

    /**
     * @return non-empty-array<PosInterface::MODEL_*, string>
     */
    public function getSecureTypeMappings(): array;

    /**
     * @return array<CreditCardInterface::CARD_TYPE_*, string>
     */
    public function getCardTypeMapping(): array;

    /**
     * @return bool
     */
    public function isTestMode(): bool;

    /**
     * @param bool $testMode
     */
    public function setTestMode(bool $testMode): void;

    /**
     * @return CryptInterface
     */
    public function getCrypt(): CryptInterface;

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     *
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     * @param string                               $paymentModel
     * @param string                               $txType
     * @param string                               $gatewayURL
     * @param CreditCardInterface|null             $creditCard
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}|non-empty-string
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null);

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     * @param string                               $txType
     * @param array<string, mixed>                 $responseData gateway'den gelen cevap
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array;

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $txType
     *
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     * @param string                               $txType
     * @param CreditCardInterface                  $creditCard
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array;

    /**
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array;

    /**
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array;

    /**
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array;

    /**
     * @phpstan-param PosInterface::TX_TYPE_REFUND* $refundTxType
     *
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     * @param string                               $refundTxType
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array;

    /**
     * @param AbstractPosAccount                   $posAccount
     * @param array<string, string|int|float|null> $order
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array;

    /**
     * @param AbstractPosAccount   $posAccount
     * @param array<string, mixed> $data bankaya gore degisen ozel degerler
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array;


    /**
     * Adds account information, constant values, calculated hash into $requestData if it is not already set.
     *
     * @param AbstractPosAccount   $posAccount
     * @param array<string, mixed> $requestData user generated request data
     *
     * @return array<string, mixed>
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array;
}
