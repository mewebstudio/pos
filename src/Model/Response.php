<?php
/**
 * @license MIT
 */
declare(strict_types=1);

namespace Mews\Pos\Model;

use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;

class Response
{
    private array $apiRawResponseData;
    private array $mappedResponseData;
    private array $order;
    private string $transaction;
    private string $gatewayClass;

    public function __construct(
        array $apiRawResponseData,
        array $mappedResponseData,
        array $order,
        string $transaction,
        string $gatewayClass

    )
    {
        $this->apiRawResponseData = $apiRawResponseData;
        $this->mappedResponseData = $mappedResponseData;
        $this->order = $order;
        $this->transaction = $transaction;
        $this->gatewayClass = $gatewayClass;
    }

    public function isSuccessful(): bool
    {
        return isset($this->mappedResponseData['status']) && ResponseDataMapperInterface::TX_APPROVED === $this->mappedResponseData['status'];
    }
}
