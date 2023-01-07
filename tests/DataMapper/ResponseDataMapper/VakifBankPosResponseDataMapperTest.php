<?php

namespace Mews\Pos\Tests\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\ResponseDataMapper\VakifBankPosResponseDataMapper;
use Mews\Pos\DataMapper\VakifBankPosRequestDataMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class VakifBankPosResponseDataMapperTest extends TestCase
{
    /** @var VakifBankPosResponseDataMapper */
    private $responseDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $requestDataMapper  = new VakifBankPosRequestDataMapper();
        $this->responseDataMapper = new VakifBankPosResponseDataMapper(
            $requestDataMapper->getCurrencyMappings(),
            $requestDataMapper->getTxTypeMappings(),
            new NullLogger()
        );
    }

    /**
     * @dataProvider threeDPaymentDataProvider
     */
    public function testMap3DPaymentData(array $threeDResponseData, array $paymentResponse, array $expectedData)
    {
        $actualData = $this->responseDataMapper->map3DPaymentData($threeDResponseData, $paymentResponse);
        unset($actualData['all']);
        unset($actualData['3d_all']);
        $this->assertSame($expectedData, $actualData);
    }

    public function threeDPaymentDataProvider(): array
    {
        return [
            'authFail1' => [
                'threeDResponseData' => [
                    'MerchantId'                => '000000000111111',
                    'SubMerchantNo'             => '0',
                    'SubMerchantName'           => null,
                    'SubMerchantNumber'         => null,
                    'PurchAmount'               => 100,
                    'PurchCurrency'             => '949',
                    'VerifyEnrollmentRequestId' => 'order-id-123',
                    'SessionInfo'               => ['data' => 'sss'],
                    'InstallmentCount'          => null,
                    'Pan'                       => '5555444433332222',
                    'Expiry'                    => 'hj',
                    'Xid'                       => 'xid0393i3kdkdlslsls',
                    'Status'                    => 'E', //diger hata durumlari N, U
                    'Cavv'                      => 'AAABBBBBBBBBBBBBBBIIIIII=',
                    'Eci'                       => '02',
                    'ExpSign'                   => '',
                    'ErrorCode'                 => '1105',
                    'ErrorMessage'              => 'Üye isyeri IP si sistemde tanimli degil',
                ],
                'paymentData' => [],
                'expectedData' => [
                    'order_id' => 'order-id-123',
                    'trans_id' => null,
                    'auth_code' => null,
                    'ref_ret_num' => null,
                    'proc_return_code' => null,
                    'status' => 'declined',
                    'status_detail' => null,
                    'error_code' => '1105',
                    'error_message' => 'Üye isyeri IP si sistemde tanimli degil',
                    'eci' => '02',
                    'cavv' => 'AAABBBBBBBBBBBBBBBIIIIII=',
                ],
            ],
            'success1' => [
                'threeDResponseData' => [
                    'MerchantId'                => '000000000111111',
                    'SubMerchantNo'             => '0',
                    'SubMerchantName'           => null,
                    'SubMerchantNumber'         => null,
                    'PurchAmount'               => 100,
                    'PurchCurrency'             => '949',
                    'VerifyEnrollmentRequestId' => 'order-id-123',
                    'SessionInfo'               => ['data' => 'sss'],
                    'InstallmentCount'          => null,
                    'Pan'                       => '5555444433332222',
                    'Expiry'                    => 'cv',
                    'Xid'                       => 'xid0393i3kdkdlslsls',
                    'Status'                    => 'Y',
                    'Cavv'                      => 'AAABBBBBBBBBBBBBBBIIIIII=',
                    'Eci'                       => '02',
                    'ExpSign'                   => null,
                    'ErrorCode'                 => null,
                    'ErrorMessage'              => null,
                ],
                'paymentData' => [
                    'MerchantId'              => '000000000111111',
                    'TerminalNo'              => 'VP999999',
                    'TransactionType'         => 'Sale',
                    'TransactionId'           => null,
                    'ResultCode'              => '0000',
                    'ResultDetail'            => 'İŞLEM BAŞARILI',
                    'CustomItems'             => [],
                    'InstallmentTable'        => null,
                    'CampaignResult'          => null,
                    'AuthCode'                => '822641',
                    'HostDate'                => '20220404123456',
                    'Rrn'                     => '209411062014',
                    'CurrencyAmount'          => 100,
                    'CurrencyCode'            => '949',
                    'OrderId'                 => 'order-id-123',
                    'TLAmount'                => 100,
                    'ECI'                     => '02',
                    'ThreeDSecureType'        => '2',
                    'TransactionDeviceSource' => '0',
                    'BatchNo'                 => '1',
                ],
                'expectedData' => [
                    'eci' => '02',
                    'cavv' => 'AAABBBBBBBBBBBBBBBIIIIII=',
                    'auth_code' => '822641',
                    'order_id' => 'order-id-123',
                    'status' => 'approved',
                    'status_detail' => 'İŞLEM BAŞARILI',
                    'error_code' => null,
                    'error_message' => null,
                    'trans_id' => null,
                    'ref_ret_num' => '209411062014',
                    'proc_return_code' => '0000',
                    'transaction_type' => 'pay',
                ]
            ]
        ];
    }
}
