<?php
/**
 * @license MIT
 */

namespace Mews\Pos;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface PosInterface
 */
interface PosInterface
{
    /** @var string */
    public const LANG_TR = 'tr';

    /** @var string */
    public const LANG_EN = 'en';

    /** @var string */
    public const TX_TYPE_PAY = 'pay';

    /** @var string */
    public const TX_TYPE_PRE_PAY = 'pre';

    /** @var string */
    public const TX_TYPE_POST_PAY = 'post';

    /** @var string */
    public const TX_TYPE_CANCEL = 'cancel';

    /** @var string */
    public const TX_TYPE_REFUND = 'refund';

    /** @var string */
    public const TX_TYPE_STATUS = 'status';

    /** @var string */
    public const TX_TYPE_HISTORY = 'history';

    /** @var string */
    public const MODEL_3D_SECURE = '3d';

    /** @var string */
    public const MODEL_3D_PAY = '3d_pay';

    /** @var string */
    public const MODEL_3D_PAY_HOSTING = '3d_pay_hosting';

    /** @var string */
    public const MODEL_3D_HOST = '3d_host';

    /** @var string */
    public const MODEL_NON_SECURE = 'regular';

    /** @var string */
    public const CURRENCY_TRY = 'TRY';

    /** @var string */
    public const CURRENCY_USD = 'USD';

    /** @var string */
    public const CURRENCY_EUR = 'EUR';

    /** @var string */
    public const CURRENCY_GBP = 'GBP';

    /** @var string */
    public const CURRENCY_JPY = 'JPY';

    /** @var string */
    public const CURRENCY_RUB = 'RUB';

    /** @var string */
    public const PAYMENT_STATUS_ERROR = 'ERROR';

    /** @var string */
    public const PAYMENT_STATUS_PAYMENT_COMPLETED = 'PAYMENT_COMPLETED';

    /** @var string */
    public const PAYMENT_STATUS_CANCELED = 'CANCELED';

    /** @var string */
    public const PAYMENT_STATUS_PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';

    /** @var string */
    public const PAYMENT_STATUS_FULLY_REFUNDED = 'FULLY_REFUNDED';

    /** @var string */
    public const PAYMENT_STATUS_PRE_AUTH_COMPLETED = 'PRE_AUTH_COMPLETED';

    /**
     * returns form data, key values, necessary for 3D payment
     *
     * @phpstan-param PosInterface::MODEL_3D_*                      $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY|PosInterface::TX_TYPE_PRE_PAY $txType
     *
     * @param array<string, mixed>     $order
     * @param string                   $paymentModel
     * @param string                   $txType
     * @param CreditCardInterface|null $card
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, ?CreditCardInterface $card = null): array;

    /**
     * Regular Payment
     * @phpstan-param PosInterface::TX_TYPE_PAY|PosInterface::TX_TYPE_PRE_PAY $txType
     *
     * @param array<string, mixed> $order
     * @param CreditCardInterface  $card
     * @param string               $txType
     *
     * @return PosInterface
     */
    public function makeRegularPayment(array $order, CreditCardInterface $card, string $txType): PosInterface;

    /**
     * Ön Provizyon kapama işlemi
     *
     * @param array<string, mixed> $order
     *
     * @return PosInterface
     */
    public function makeRegularPostPayment(array $order): PosInterface;

    /**
     * Make 3D Payment
     * @phpstan-param PosInterface::TX_TYPE_PAY|PosInterface::TX_TYPE_PRE_PAY $txType
     *
     * @param Request                  $request
     * @param array<string, mixed>     $order
     * @param string                   $txType
     * @param CreditCardInterface|null $card simdilik sadece PayFlexV4Pos icin card isteniyor.
     *
     * @return PosInterface
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $card = null): PosInterface;

    /**
     * Just returns formatted data of 3d_pay payment response
     * @phpstan-param PosInterface::TX_TYPE_PAY|PosInterface::TX_TYPE_PRE_PAY $txType
     *
     * @param Request              $request
     * @param array<string, mixed> $order
     * @param string               $txType
     *
     * @return PosInterface
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface;

    /**
     * Just returns formatted data of host payment response
     * @phpstan-param PosInterface::TX_TYPE_PAY|PosInterface::TX_TYPE_PRE_PAY $txType
     *
     * @param Request              $request
     * @param array<string, mixed> $order
     * @param string               $txType
     *
     * @return PosInterface
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface;

    /**
     * Main Payment method
     *
     * can be used for all kind of payment transactions and payment models
     *
     * @phpstan-param PosInterface::MODEL_*                                                   $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY|PosInterface::TX_TYPE_PRE_PAY|PosInterface::TX_TYPE_POST_PAY $txType
     *
     * @param string                   $paymentModel
     * @param array<string, mixed>     $order
     * @param string                   $txType
     * @param CreditCardInterface|null $card
     *
     * @return PosInterface
     *
     * @throws UnsupportedPaymentModelException
     */
    public function payment(string $paymentModel, array $order, string $txType, ?CreditCardInterface $card = null): PosInterface;

    /**
     * Refund Order
     *
     * @param array<string, mixed> $order
     *
     * @return PosInterface
     */
    public function refund(array $order): PosInterface;

    /**
     * Cancel Order
     *
     * @param array<string, mixed> $order
     *
     * @return PosInterface
     */
    public function cancel(array $order): PosInterface;

    /**
     * Order Status
     *
     * @param array<string, mixed> $order
     *
     * @return PosInterface
     */
    public function status(array $order): PosInterface;

    /**
     * Order History
     *
     * @param array<string, mixed> $meta
     *
     * @return PosInterface
     */
    public function history(array $meta): PosInterface;

    /**
     * Is success
     *
     * @return bool
     */
    public function isSuccess(): bool;

    /**
     * returns the latest response
     *
     * @return array<string, mixed>|null
     */
    public function getResponse(): ?array;

    /**
     * Enable/Disable test mode
     *
     * @param bool $testMode
     *
     * @return PosInterface
     */
    public function setTestMode(bool $testMode): PosInterface;

    /**
     * Enable/Disable test mode
     *
     * @return bool
     */
    public function isTestMode(): bool;

    /**
     * @return array<CreditCardInterface::CARD_TYPE_*, string>
     */
    public function getCardTypeMapping(): array;

    /**
     * @return AbstractPosAccount
     */
    public function getAccount(): AbstractPosAccount;

    /**
     * @phpstan-param PosInterface::TX_TYPE_* $txType
     * @phpstan-param PosInterface::MODEL_* $paymentModel
     *
     * @param string $txType
     * @param string $paymentModel
     *
     * @return bool
     */
    public static function isSupportedTransaction(string $txType, string $paymentModel): bool;
}
