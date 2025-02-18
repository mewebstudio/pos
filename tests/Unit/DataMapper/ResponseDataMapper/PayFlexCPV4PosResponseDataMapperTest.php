<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper;

use Generator;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexCPV4PosResponseDataMapper;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\PayFlexCPV4PosResponseDataMapper
 * @covers \Mews\Pos\DataMapper\ResponseDataMapper\AbstractResponseDataMapper
 */
class PayFlexCPV4PosResponseDataMapperTest extends TestCase
{
    private PayFlexCPV4PosResponseDataMapper $responseDataMapper;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $requestDataMapper        = new PayFlexCPV4PosRequestDataMapper(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CryptInterface::class),
        );
        $this->responseDataMapper = new PayFlexCPV4PosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            $requestDataMapper->getSecureTypeMappings(),
            $this->logger,
        );
    }

    /**
     * @testWith [null, true]
     * ["", true]
     * ["21", true]
     *
     */
    public function testIs3dAuthSuccess(?string $mdStatus, bool $expected): void
    {
        $actual = $this->responseDataMapper->is3dAuthSuccess($mdStatus);
        $this->assertSame($expected, $actual);
    }


    /**
     * @testWith [[], null]
     * [{"blabla": "1"}, null]
     *
     */
    public function testExtractMdStatus(array $responseData, ?string $expected): void
    {
        $actual = $this->responseDataMapper->extractMdStatus($responseData);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider threesDPayResponseDataProvider
     */
    public function testMap3DPayResponseData(array $order, string $txType, array $bankResponse, array $expectedData): void
    {
        $actualData = $this->responseDataMapper->map3DPayResponseData($bankResponse, $txType, $order);
        $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
        unset($actualData['transaction_time'], $expectedData['transaction_time']);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    /**
     * @dataProvider threesDPayResponseDataProvider
     */
    public function testMap3DHostResponseData(array $order, string $txType, array $bankResponse, array $expectedData): void
    {
        $expectedData['payment_model'] = PosInterface::MODEL_3D_HOST;
        $actualData = $this->responseDataMapper->map3DHostResponseData($bankResponse, $txType, $order);
        $this->assertEquals($expectedData['transaction_time'], $actualData['transaction_time']);
        unset($actualData['transaction_time'], $expectedData['transaction_time']);

        $this->assertArrayHasKey('all', $actualData);
        $this->assertIsArray($actualData['all']);
        $this->assertNotEmpty($actualData['all']);
        unset($actualData['all']);

        \ksort($expectedData);
        \ksort($actualData);
        $this->assertSame($expectedData, $actualData);
    }

    public function testMap3DPaymentResponseData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->map3DPaymentData([], [], PosInterface::TX_TYPE_PAY_AUTH, []);
    }

    public function testMapPaymentResponse(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->responseDataMapper->mapPaymentResponse([], PosInterface::TX_TYPE_PAY_AUTH, []);
    }

    public function testMapRefundResponse(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->responseDataMapper->mapRefundResponse([]);
    }

    public function testMapCancelResponse(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->responseDataMapper->mapCancelResponse([]);
    }

    public function testMapStatusResponse(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->responseDataMapper->mapStatusResponse([]);
    }

    public function testMapHistoryResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapHistoryResponse([]);
    }

    public function testMapOrderHistoryResponse(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->responseDataMapper->mapOrderHistoryResponse([]);
    }

    public static function threesDPayResponseDataProvider(): Generator
    {
        yield 'fail_response_from_gateway_1' => [
            'order'         => [],
            'txType'        => PosInterface::TX_TYPE_PAY_AUTH,
            'bank_response' => [
                'Rc'            => '2053',
                'Message'       => 'VeRes status is E Message : Directory server communication error',
                'PaymentToken'  => '68244b7e3dfd4b3ebea1afbe0185b9ed',
                'TransactionId' => '0cb6a57715144178a014afbe0185b9ed',
                'MaskedPan'     => '49384601****4205',
            ],
            'expectedData'  => [
                'order_id'             => null,
                'transaction_id'       => '0cb6a57715144178a014afbe0185b9ed',
                'transaction_type'     => 'pay',
                'transaction_time'     => null,
                'transaction_security' => null,
                'auth_code'            => null,
                'ref_ret_num'          => null,
                'batch_num'            => null,
                'proc_return_code'     => '2053',
                'status'               => 'declined',
                'status_detail'        => null,
                'error_code'           => '2053',
                'error_message'        => 'VeRes status is E Message : Directory server communication error',
                'md_status'            => null,
                'md_error_message'     => null,
                'masked_number'        => '49384601****4205',
                'currency'             => null,
                'amount'               => null,
                'payment_model'        => '3d_pay',
                'installment_count'    => null,
            ],
        ];

        yield 'fail_response_from_gateway_2' => [
            'order'         => [],
            'txType'        => PosInterface::TX_TYPE_PAY_AUTH,
            'bank_response' => [
                'Rc'            => '0057',
                'AuthCode'      => '000000',
                'ErrorCode'     => '0057',
                'Message'       => 'RED-KARTIN İŞLEM İZNİ YOK',
                'PaymentToken'  => '5aeb359892f8400b9d0fafbd016c7636',
                'TransactionId' => '868382724da7480c949dafbd016c7636',
                'MaskedPan'     => '49384601****4205',
            ],
            'expectedData'  => [
                'order_id'             => null,
                'transaction_id'       => '868382724da7480c949dafbd016c7636',
                'transaction_type'     => 'pay',
                'transaction_time'     => null,
                'transaction_security' => null,
                'auth_code'            => null,
                'ref_ret_num'          => null,
                'batch_num'            => null,
                'proc_return_code'     => '0057',
                'status'               => 'declined',
                'status_detail'        => null,
                'error_code'           => '0057',
                'error_message'        => 'RED-KARTIN İŞLEM İZNİ YOK',
                'md_status'            => null,
                'md_error_message'     => null,
                'masked_number'        => '49384601****4205',
                'currency'             => null,
                'amount'               => null,
                'payment_model'        => '3d_pay',
                'installment_count'    => null,
            ],
        ];

        yield 'success_response_from_gateway_1' => [
            'order'         => [],
            'txType'        => PosInterface::TX_TYPE_PAY_AUTH,
            'bank_response' => [
                'Rc'                   => '0000',
                'AuthCode'             => '735879',
                'Rrn'                  => '306822971283',
                'Message'              => 'İŞLEM BAŞARILI',
                'TransactionId'        => '3ee068d5b5a747ada65dafc0016d5887',
                'PaymentToken'         => 'b35a56bf37334872a945afc0016d5887',
                'MaskedPan'            => '49384601****4205',
                'HostMerchantId'       => '000100000013506',
                'HostTerminalId'       => 'VP000579',
                'AmountCode'           => '949',
                'Amount'               => '1,01',
                'TransactionType'      => 'Sale',
                'OrderID'              => '2023030913ED',
                'InstallmentCount'     => '',
                'IsSecure'             => 'True',
                'AllowNotEnrolledCard' => 'True',
                'SuccessUrl'           => 'http://localhost/vakifbank-cp/3d-host/response.php',
                'FailUrl'              => 'http://localhost/vakifbank-cp/3d-host/response.php',
                'RequestLanguage'      => 'tr-TR',
                'Extract'              => null,
                'CardHoldersName'      => 'Jo* Do*',
                'CustomItems'          => null,
                'ExpireMonth'          => '11',
                'ExpireYear'           => '2024',
                'BrandNumber'          => '100',
                'HostDate'             => '20230309221037',
                'HostRc'               => null,
                'CampaignResult'       => [
                    'CampaignInfo' => [],
                ],
                'ErrorCode'            => null,
                'ResponseMessage'      => null,
            ],
            'expectedData'  => [
                'order_id'             => '2023030913ED',
                'transaction_id'       => '3ee068d5b5a747ada65dafc0016d5887',
                'transaction_type'     => 'pay',
                'transaction_time'     => new \DateTimeImmutable('2023-03-09 22:10:37'),
                'transaction_security' => null,
                'auth_code'            => '735879',
                'ref_ret_num'          => '3ee068d5b5a747ada65dafc0016d5887',
                'batch_num'            => null,
                'proc_return_code'     => '0000',
                'status'               => 'approved',
                'status_detail'        => null,
                'error_code'           => null,
                'error_message'        => null,
                'md_status'            => null,
                'md_error_message'     => null,
                'masked_number'        => '49384601****4205',
                'currency'             => 'TRY',
                'amount'               => 1.0,
                'payment_model'        => '3d_pay',
                'installment_count'    => 0,
            ],
        ];
    }
}
