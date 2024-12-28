<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

interface NonPaymentResponseMapperInterface
{
    /**
     * @param array<string, string> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapRefundResponse(array $rawResponseData): array;

    /**
     * @param array<string, string> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapCancelResponse(array $rawResponseData): array;

    /**
     * @param array<string, mixed> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapStatusResponse(array $rawResponseData): array;

    /**
     * @param array<string, array<string, string>|string> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapHistoryResponse(array $rawResponseData): array;

    /**
     * @param array<string, array<string, string>|string> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapOrderHistoryResponse(array $rawResponseData): array;
}
