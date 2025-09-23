<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

interface SoapClientInterface
{
    /**
     * @param string               $url
     * @param string               $soapAction
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed> soap result
     *
     * @throws \SoapFault
     */
    public function call(string $url, string $soapAction, array $data, array $options = []): array;

    /**
     * @return bool
     */
    public function isTestMode(): bool;

    /**
     * @param bool $isTestMode
     *
     * @return void
     */
    public function setTestMode(bool $isTestMode): void;
}
