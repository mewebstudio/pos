<?php

/**
 * @license MIT
 */

namespace Mews\Pos;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Psr\Http\Client\ClientExceptionInterface;
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
    public const TX_TYPE_PAY_AUTH = 'pay';

    /** @var string */
    public const TX_TYPE_PAY_PRE_AUTH = 'pre';

    /** @var string */
    public const TX_TYPE_PAY_POST_AUTH = 'post';

    /** @var string */
    public const TX_TYPE_CANCEL = 'cancel';

    /** @var string */
    public const TX_TYPE_REFUND = 'refund';

    /** @var string */
    public const TX_TYPE_REFUND_PARTIAL = 'refund_partial';

    /** @var string */
    public const TX_TYPE_STATUS = 'status';

    /** @var string */
    public const TX_TYPE_ORDER_HISTORY = 'order_history';

    /** @var string */
    public const TX_TYPE_HISTORY = 'history';

    /** @var string */
    public const TX_TYPE_CUSTOM_QUERY = 'custom_query';

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
    public const PAYMENT_STATUS_PAYMENT_PENDING = 'PAYMENT_PENDING';

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
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param array<string, mixed>     $order
     * @param string                   $paymentModel
     * @param string                   $txType
     * @param CreditCardInterface|null $creditCard
     * @param bool                     $createWithoutCard 3D ve 3D_PAY ödemelerde kart bilgisi olmadan 3D formu oluşturulmasına izin verir.
     *
     * @return non-empty-string|array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>} Banka response'u HTML olduğu durumda string döner.
     *
     * @throws \RuntimeException when request to the bank to get 3D form data failed
     * @throws ClientExceptionInterface when request to the bank to get 3D form data failed
     * @throws \InvalidArgumentException when card data is not provided when it is required for the given payment model
     * @throws \LogicException when given payment model or transaction type is not supported
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, ?CreditCardInterface $creditCard = null, bool $createWithoutCard = true);

    /**
     * Regular Payment
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param array<string, mixed> $order
     * @param CreditCardInterface  $creditCard
     * @param string               $txType
     *
     * @return PosInterface
     *
     * @throws \LogicException
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    public function makeRegularPayment(array $order, CreditCardInterface $creditCard, string $txType): PosInterface;

    /**
     * Ön Provizyon kapama işlemi
     *
     * @param array<string, mixed> $order
     *
     * @return PosInterface
     *
     * @throws UnsupportedPaymentModelException
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    public function makeRegularPostPayment(array $order): PosInterface;

    /**
     * Make 3D Payment
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param Request                  $request
     * @param array<string, mixed>     $order
     * @param string                   $txType
     * @param CreditCardInterface|null $creditCard simdilik sadece PayFlexV4Pos icin card isteniyor.
     *
     * @return PosInterface
     *
     * @throws HashMismatchException
     * @throws UnsupportedTransactionTypeException
     * @throws UnsupportedPaymentModelException
     * @throws ClientExceptionInterface
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $creditCard = null): PosInterface;

    /**
     * Just returns formatted data of 3d_pay payment response
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param Request              $request
     * @param array<string, mixed> $order
     * @param string               $txType
     *
     * @return PosInterface
     *
     * @throws HashMismatchException
     * @throws UnsupportedTransactionTypeException
     * @throws UnsupportedPaymentModelException
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface;

    /**
     * Just returns formatted data of host payment response
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param Request              $request
     * @param array<string, mixed> $order
     * @param string               $txType
     *
     * @return PosInterface
     *
     * @throws HashMismatchException
     * @throws UnsupportedTransactionTypeException
     * @throws UnsupportedPaymentModelException
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface;

    /**
     * Main Payment method
     *
     * can be used for all kind of payment transactions and payment models
     *
     * @phpstan-param PosInterface::MODEL_*       $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $txType
     *
     * @param string                   $paymentModel
     * @param array<string, mixed>     $order
     * @param string                   $txType
     * @param CreditCardInterface|null $creditCard
     *
     * @return PosInterface
     *
     * @throws UnsupportedPaymentModelException
     * @throws UnsupportedTransactionTypeException
     * @throws \LogicException
     * @throws ClientExceptionInterface
     */
    public function payment(string $paymentModel, array $order, string $txType, ?CreditCardInterface $creditCard = null): PosInterface;

    /**
     * Refund Order
     *
     * @param array<string, mixed> $order
     *
     * @return PosInterface
     *
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    public function refund(array $order): PosInterface;

    /**
     * Cancel Order
     *
     * @param array<string, mixed> $order
     *
     * @return PosInterface
     *
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    public function cancel(array $order): PosInterface;

    /**
     * Order Status
     *
     * @param array<string, mixed> $order
     *
     * @return PosInterface
     *
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    public function status(array $order): PosInterface;

    /**
     * Order History
     *
     * @param array<string, mixed> $order
     *
     * @return PosInterface
     *
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    public function orderHistory(array $order): PosInterface;

    /**
     * @param array<string, mixed> $data
     *
     * @return PosInterface
     *
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    public function history(array $data): PosInterface;


    /**
     * Kütüphanenin desteği olmadığı özel istekleri bu methodla yapabilirsiniz.
     * requestData içinde API hesap bilgileri, hash verisi ve bazi sabit değerler
     * eğer zaten bulunmuyorsa kütüphane otomatik ekler.
     *
     * Bankadan dönen cevap array'e dönüştürülür,
     * ancak diğer transaction'larda olduğu gibi mapping/normalization yapılmaz.
     *
     * @param array<string, mixed>  $requestData API'a gönderilecek veri.
     * @param non-empty-string|null $apiUrl
     *
     * @return PosInterface
     *
     * @throws ClientExceptionInterface
     */
    public function customQuery(array $requestData, string $apiUrl = null): PosInterface;

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
     * returns the list of supported currencies
     *
     * @return non-empty-array<int, PosInterface::CURRENCY_*>
     */
    public function getCurrencies(): array;

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
