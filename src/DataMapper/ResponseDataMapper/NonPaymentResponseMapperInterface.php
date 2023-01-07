<?php

namespace Mews\Pos\DataMapper\ResponseDataMapper;

interface NonPaymentResponseMapperInterface
{
    /**
     * @param array<string, string> $rawResponseData
     *
     * @return array<string, string>
     */
    public function mapRefundResponse(array $rawResponseData): array;

    /**
     * @param array<string, string> $rawResponseData
     *
     * @return array<string, string>
     */
    public function mapCancelResponse(array $rawResponseData): array;

    /**
     * @param array<string, string> $rawResponseData
     *
     * @return array<string, string|float|null>
     */
    public function mapStatusResponse(array $rawResponseData): array;

    /**
     * @param array<string, array<string, string>|string> $rawResponseData
     *
     * @return array<string, array<string, string|string|null>>
     */
    public function mapHistoryResponse(array $rawResponseData): array;
}
