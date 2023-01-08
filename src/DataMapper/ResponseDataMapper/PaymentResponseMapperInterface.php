<?php

namespace Mews\Pos\DataMapper\ResponseDataMapper;

interface PaymentResponseMapperInterface
{
    /**
     * @param array<string, string> $rawPaymentResponseData
     *
     * @return array<string, string|float|null>
     */
    public function mapPaymentResponse(array $rawPaymentResponseData): array;

    /**
     * @param array<string, string>      $raw3DAuthResponseData
     * @param array<string, string>|null $rawPaymentResponseData null when payment request was not made
     *
     * @return array<string, string|float|null>
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData): array;

    /**
     * @param array<string, string> $raw3DAuthResponseData
     *
     * @return array<string, string|float|null>
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData): array;

    /**
     * @param array<string, string> $raw3DAuthResponseData
     *
     * @return array<string, string|float|null>
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData): array;
}
