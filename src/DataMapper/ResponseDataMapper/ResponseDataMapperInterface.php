<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

interface ResponseDataMapperInterface extends PaymentResponseMapperInterface, NonPaymentResponseMapperInterface
{
    /** @var string */
    public const TX_APPROVED = 'approved';

    /** @var string */
    public const TX_DECLINED = 'declined';
}
