<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseValueMapper;

use Mews\Pos\PosInterface;

abstract class AbstractResponseValueMapper implements ResponseValueMapperInterface
{
    /** @var array<string|int, PosInterface::CURRENCY_*> */
    protected array $currencyMappings = [];

    /** @var array<PosInterface::TX_TYPE_*, string|array<PosInterface::MODEL_*, string>> */
    protected array $txTypeMappings = [];

    /** @var array<string|int, PosInterface::MODEL_*> */
    protected array $secureTypeMappings = [];

    /**
     * @var array<string|int, PosInterface::PAYMENT_STATUS_*>
     */
    protected array $orderStatusMappings = [];

    /**
     * @param array<PosInterface::CURRENCY_*, string|int>                                 $currencyMappings
     * @param array<PosInterface::TX_TYPE_*, string|array<PosInterface::MODEL_*, string>> $txTypeMappings
     * @param array<PosInterface::MODEL_*, string|int>                                    $secureTypeMappings
     */
    public function __construct(
        array $currencyMappings,
        array $txTypeMappings,
        array $secureTypeMappings
    ) {
        if ([] !== $currencyMappings) {
            $this->currencyMappings = \array_flip($currencyMappings);
        }

        if ([] !== $txTypeMappings) {
            $this->txTypeMappings = $txTypeMappings;
        }

        if ([] !== $secureTypeMappings) {
            $this->secureTypeMappings = \array_flip($secureTypeMappings);
        }
    }

    /**
     * @inheritDoc
     */
    public function mapTxType($txType, ?string $paymentModel = null): ?string
    {
        if ([] === $this->txTypeMappings) {
            throw new \LogicException('Transaction type mapping is not supported');
        }

        foreach ($this->txTypeMappings as $mappedTxType => $mapping) {
            if (\is_array($mapping) && \in_array($txType, $mapping, true)) {
                return $mappedTxType;
            }

            if ($mapping === $txType) {
                return $mappedTxType;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function mapSecureType(string $securityType, ?string $apiRequestTxType = null): ?string
    {
        if ([] === $this->secureTypeMappings) {
            throw new \LogicException('Secure type mapping is not supported');
        }

        return $this->secureTypeMappings[$securityType] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function mapCurrency($currency, ?string $apiRequestTxType = null): ?string
    {
        return $this->currencyMappings[$currency] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function mapOrderStatus($orderStatus)
    {
        if ([] === $this->orderStatusMappings) {
            throw new \LogicException('Order status mapping is not supported.');
        }

        return $this->orderStatusMappings[$orderStatus] ?? $orderStatus;
    }
}
