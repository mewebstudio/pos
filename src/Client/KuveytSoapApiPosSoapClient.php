<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Gateways\KuveytSoapApiPos;

/**
 * Soap Client Wrapper
 */
class KuveytSoapApiPosSoapClient extends AbstractSoapClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return KuveytSoapApiPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestData(array $requestData): array
    {
        return ['parameters' => ['request' => $requestData]];
    }
}
