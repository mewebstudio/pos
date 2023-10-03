<?php

namespace Mews\Pos\Tests\DataMapper\ResponseDataMapper;

use Generator;
use Mews\Pos\DataMapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexCPV4PosResponseDataMapper;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;


class PayFlexCPV4PosResponseDataMapperTest extends TestCase
{
    /** @var PayFlexCPV4PosResponseDataMapper */
    private $responseDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $crypt                    = PosFactory::getGatewayCrypt(PayFlexCPV4Pos::class, new NullLogger());
        $requestDataMapper        = new PayFlexCPV4PosRequestDataMapper($this->createMock(EventDispatcherInterface::class), $crypt);
        $this->responseDataMapper = new PayFlexCPV4PosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            new NullLogger()
        );
    }

    /**
     * @dataProvider threesDPayResponseSamplesProvider
     */
    public function testMap3DPayResponseData(array $bankResponse, array $expected): void
    {
        $actual = $this->responseDataMapper->map3DPayResponseData($bankResponse);

        $this->assertNotEmpty($actual['all']);
        unset($actual['all']);
        $this->assertSame($expected, $actual);
    }

    public static function threesDPayResponseSamplesProvider(): Generator
    {
        yield 'fail_response_from_gateway_1' => [
            'bank_response' => [
                'Rc'            => '2053',
                'Message'       => 'VeRes status is E Message : Directory server communication error',
                'PaymentToken'  => '68244b7e3dfd4b3ebea1afbe0185b9ed',
                'TransactionId' => '0cb6a57715144178a014afbe0185b9ed',
                'MaskedPan'     => '49384601****4205',
            ],
            'expected'      => [
                'order_id'             => null,
                'trans_id'             => '0cb6a57715144178a014afbe0185b9ed',
                'auth_code'            => null,
                'ref_ret_num'          => null,
                'proc_return_code'     => '2053',
                'status'               => 'declined',
                'status_detail'        => null,
                'error_code'           => '2053',
                'error_message'        => 'VeRes status is E Message : Directory server communication error',
                'md_status'            => null,
                'md_error_message'     => null,
                'transaction_security' => null,
                'masked_number'        => '49384601****4205',
            ],
        ];

        yield 'fail_response_from_gateway_2' => [
            'bank_response' => [
                'Rc'            => '0057',
                'AuthCode'      => '000000',
                'ErrorCode'     => '0057',
                'Message'       => 'RED-KARTIN İŞLEM İZNİ YOK',
                'PaymentToken'  => '5aeb359892f8400b9d0fafbd016c7636',
                'TransactionId' => '868382724da7480c949dafbd016c7636',
                'MaskedPan'     => '49384601****4205',
            ],
            'expected'      => [
                'order_id'             => null,
                'trans_id'             => '868382724da7480c949dafbd016c7636',
                'auth_code'            => null,
                'ref_ret_num'          => null,
                'proc_return_code'     => '0057',
                'status'               => 'declined',
                'status_detail'        => null,
                'error_code'           => '0057',
                'error_message'        => 'RED-KARTIN İŞLEM İZNİ YOK',
                'md_status'            => null,
                'md_error_message'     => null,
                'transaction_security' => null,
                'masked_number'        => '49384601****4205',
            ],
        ];

        yield 'success_response_from_gateway_1' => [
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
                'OrderDescription'     => null,
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
            'expected'      => [
                'order_id'             => '2023030913ED',
                'trans_id'             => '3ee068d5b5a747ada65dafc0016d5887',
                'auth_code'            => '735879',
                'ref_ret_num'          => '3ee068d5b5a747ada65dafc0016d5887',
                'proc_return_code'     => '0000',
                'status'               => 'approved',
                'status_detail'        => null,
                'error_code'           => null,
                'error_message'        => null,
                'md_status'            => null,
                'md_error_message'     => null,
                'transaction_security' => null,
                'masked_number'        => '49384601****4205',
            ],
        ];
    }
}
