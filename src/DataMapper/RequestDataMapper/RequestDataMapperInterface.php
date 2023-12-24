<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\PosInterface;

interface RequestDataMapperInterface
{
    /**
     * @return array<PosInterface::TX_TYPE_*, string>
     */
    public function getTxTypeMappings(): array;

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
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     * @param string                               $paymentModel
     * @param string                               $txType
     * @param string                               $gatewayURL
     * @param CreditCardInterface|null             $card
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}
     */
    public function create3DFormData(AbstractPosAccount $account, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $card = null): array;

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     * @param string                               $txType
     * @param array<string, mixed>                 $responseData gateway'den gelen cevap
     *
     * @return array<string, mixed>
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, array $order, string $txType, array $responseData): array;

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $txType
     *
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     * @param string                               $txType
     * @param CreditCardInterface                  $card
     *
     * @return array<string, mixed>
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, string $txType, CreditCardInterface $card): array;

    /**
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     *
     * @return array<string, mixed>
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, array $order): array;

    /**
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     *
     * @return array<string, mixed>
     */
    public function createStatusRequestData(AbstractPosAccount $account, array $order): array;

    /**
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     *
     * @return array<string, mixed>
     */
    public function createCancelRequestData(AbstractPosAccount $account, array $order): array;

    /**
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     *
     * @return array<string, mixed>
     */
    public function createRefundRequestData(AbstractPosAccount $account, array $order): array;

    /**
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     * @param array<string, string|int|float|null> $extraData bankaya gore degisen ozel degerler
     *
     * @return array<string, mixed>
     */
    public function createHistoryRequestData(AbstractPosAccount $account, array $order, array $extraData = []): array;
}
