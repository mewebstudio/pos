<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestDataMapper\Param3DHostPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestValueFormatter\ParamPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueMapper\ParamPosRequestValueMapper;
use Mews\Pos\Entity\Account\ParamPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\Param3DHostPosRequestDataMapper
 * @covers \Mews\Pos\DataMapper\RequestDataMapper\AbstractRequestDataMapper
 */
class Param3DHostPosRequestDataMapperTest extends TestCase
{
    private ParamPosAccount $account;

    /** @var CryptInterface & MockObject */
    private CryptInterface $crypt;

    /** @var EventDispatcherInterface & MockObject */
    private EventDispatcherInterface $dispatcher;

    private ParamPosRequestValueFormatter $valueFormatter;

    private ParamPosRequestValueMapper $valueMapper;

    private Param3DHostPosRequestDataMapper $requestDataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createParamPosAccount(
            'param-pos',
            10738,
            'Test1',
            'Test2',
            '0c13d406-873b-403b-9c09-a5766840d98c'
        );

        $this->dispatcher     = $this->createMock(EventDispatcherInterface::class);
        $this->crypt          = $this->createMock(CryptInterface::class);
        $this->valueFormatter = new ParamPosRequestValueFormatter();
        $this->valueMapper    = new ParamPosRequestValueMapper();

        $this->requestDataMapper = new Param3DHostPosRequestDataMapper(
            $this->valueMapper,
            $this->valueFormatter,
            $this->dispatcher,
            $this->crypt,
        );
    }

    public function testSupports(): void
    {
        $result = $this->requestDataMapper::supports(Param3DHostPos::class);
        $this->assertTrue($result);

        $result = $this->requestDataMapper::supports(EstV3Pos::class);
        $this->assertFalse($result);
    }

    public function testCreateNonSecurePostAuthPaymentRequestData(): void
    {
        $this->expectException(\Mews\Pos\Exceptions\NotImplementedException::class);
        $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, []);
    }

    /**
     * @dataProvider paymentRegisterRequestDataProvider
     */
    public function testCreate3DEnrollmentCheckRequestData(array $order, string $txType, string $soapAction, array $expected): void
    {
        $soapBody = $expected['soap:Body'];
        $this->crypt->expects(self::once())
            ->method('generateRandomString')
            ->willReturn($soapBody[$soapAction]['Islem_ID']);

        $actual = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $this->account,
            $order,
            $txType
        );

        ksort($actual);
        ksort($expected);
        ksort($actual['soap:Body'][$soapAction]);
        ksort($expected['soap:Body'][$soapAction]);
        $this->assertSame($expected, $actual);
    }

    public function testCreateNonSecurePaymentRequestData(): void
    {
        $this->expectException(NotImplementedException::class);

        $this->requestDataMapper->createNonSecurePaymentRequestData(
            $this->account,
            [],
            PosInterface::TX_TYPE_PAY_AUTH,
            $this->createMock(CreditCardInterface::class)
        );
    }

    public function testCreateCancelRequestData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->createCancelRequestData($this->account, []);
    }

    public function testCreateOrderHistoryRequestData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->createOrderHistoryRequestData($this->account, []);
    }


    /**
     * @dataProvider threeDFormDataProvider
     */
    public function testGet3DFormData(
        array   $order,
        string  $txType,
        ?string $gatewayURL,
        array   $extraData,
        $expected
    ): void {
        $this->crypt->expects(self::never())
            ->method('create3DHash');

        $this->crypt->expects(self::never())
            ->method('generateRandomString');

        $this->dispatcher->expects(self::never())
            ->method('dispatch');

        $actual = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            PosInterface::MODEL_3D_HOST,
            $txType,
            $gatewayURL,
            null,
            $extraData
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider threeDFormDataProviderFail
     */
    public function testGet3DFormDataFail(
        array   $order,
        string  $txType,
        string  $paymentModel,
        ?string $gatewayURL,
        array   $extraData,
        string  $expectedException
    ): void {
        $this->crypt->expects(self::never())
            ->method('create3DHash');

        $this->crypt->expects(self::never())
            ->method('generateRandomString');

        $this->dispatcher->expects(self::never())
            ->method('dispatch');

        $this->expectException($expectedException);

        $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayURL,
            null,
            $extraData
        );
    }

    public function testCreateStatusRequestData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->createStatusRequestData($this->account, []);
    }

    public function testCreateRefundRequestData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->createRefundRequestData($this->account, [], PosInterface::TX_TYPE_REFUND);
    }

    public function testCreate3DPaymentRequestData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->create3DPaymentRequestData(
            $this->account,
            [],
            PosInterface::TX_TYPE_PAY_AUTH,
            []
        );
    }

    public function testCreateHistoryRequestData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->createHistoryRequestData($this->account, []);
    }

    public function testCreateCustomQueryRequestData(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->requestDataMapper->createCustomQueryRequestData($this->account, []);
    }

    public static function paymentRegisterRequestDataProvider(): array
    {
        $order = [
            'id'          => 'order222',
            'amount'      => 1000.25,
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'ip'          => '127.0.0.1',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail',
        ];

        return [
            '3d_host'                  => [
                'order'      => $order,
                'txType'     => PosInterface::TX_TYPE_PAY_AUTH,
                'soapAction' => 'TO_Pre_Encrypting_OOS',
                'expected'   => [
                    'soap:Body'   => [
                        'TO_Pre_Encrypting_OOS' => [
                            '@xmlns'           => 'https://turkodeme.com.tr/',
                            'Borclu_Aciklama'  => 'r|',
                            'Borclu_AdSoyad'   => 'r|',
                            'Borclu_GSM'       => 'r|',
                            'Borclu_Kisi_TC'   => '',
                            'Borclu_Odeme_Tip' => 'r|Diğer',
                            'Borclu_Tutar'     => 'r|1000,25',
                            'Islem_ID'         => 'rand',
                            'Return_URL'       => 'r|https://domain.com/success',
                            'Taksit'           => '1',
                            'Terminal_ID'      => '10738',
                        ],
                    ],
                    'soap:Header' => [
                        'ServiceSecuritySoapHeader' => [
                            '@xmlns'          => 'https://turkodeme.com.tr/',
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                    ],
                ],
            ],
            '3d_host_foreign_currency' => [
                'order'      => [
                    'id'          => 'order222',
                    'amount'      => 1000.25,
                    'installment' => 0,
                    'currency'    => PosInterface::CURRENCY_EUR,
                    'ip'          => '127.0.0.1',
                    'success_url' => 'https://domain.com/success',
                    'fail_url'    => 'https://domain.com/fail',
                ],
                'txType'     => PosInterface::TX_TYPE_PAY_AUTH,
                'soapAction' => 'TO_Pre_Encrypting_OOS',
                'expected'   => [
                    'soap:Body'   => [
                        'TO_Pre_Encrypting_OOS' => [
                            '@xmlns'           => 'https://turkodeme.com.tr/',
                            'Borclu_Aciklama'  => 'r|',
                            'Borclu_AdSoyad'   => 'r|',
                            'Borclu_GSM'       => 'r|',
                            'Borclu_Kisi_TC'   => '',
                            'Borclu_Odeme_Tip' => 'r|Diğer',
                            'Borclu_Tutar'     => 'r|1000,25',
                            'Doviz_Kodu'       => '1002',
                            'Islem_ID'         => 'rand',
                            'Return_URL'       => 'r|https://domain.com/success',
                            'Taksit'           => '1',
                            'Terminal_ID'      => '10738',
                        ],
                    ],
                    'soap:Header' => [
                        'ServiceSecuritySoapHeader' => [
                            '@xmlns'          => 'https://turkodeme.com.tr/',
                            'CLIENT_CODE'     => '10738',
                            'CLIENT_USERNAME' => 'Test1',
                            'CLIENT_PASSWORD' => 'Test2',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function threeDFormDataProvider(): array
    {
        return [
            '3d_host_form_data' => [
                'order'      => [],
                'tx_type'    => PosInterface::TX_TYPE_PAY_AUTH,
                'gateway'    => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                'extra_data' => [
                    'TO_Pre_Encrypting_OOSResponse' => [
                        'TO_Pre_Encrypting_OOSResult' => 'JHnDLmT5yierHIqsHNRU2SR7HLxOpi8o7Eb/oVSiIf35v+Z1uzteqid4wop8SAuykWNFElYyAxGWcIGvTxmhSljuLTcJ3xDMkS3O0jUboNpl5ad6roy/92lDftpV535KmpbxMxStRa+qGT7Tk4BdEIf+Jobr2o1Yl1+ZakWZ+parsTgnodyWl432Hsv2FUNLhuU7H6folMwleaZFPYdFZ+bO1T95opw5pnDWcFkrIuPfAmVRg4cg+al22FQSN/58AXxWBb8jEPrqn+/ojZ+WqncGvw+NB/Mtv9iCDuF+SNQqRig2dRILzWYwcvNxzj/OxcYuNuvO8wYI/iF1kNBBNtaExIunWZyj1tntGeb7UUaDmHD4LmSMUMpgZGugRfUpxm8WL/EE+PnUkLXE7SOG3g==',
                    ],
                ],
                'expected'   => [
                    'gateway' => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                    'method'  => 'GET',
                    'inputs'  => [
                        's' => 'JHnDLmT5yierHIqsHNRU2SR7HLxOpi8o7Eb/oVSiIf35v+Z1uzteqid4wop8SAuykWNFElYyAxGWcIGvTxmhSljuLTcJ3xDMkS3O0jUboNpl5ad6roy/92lDftpV535KmpbxMxStRa+qGT7Tk4BdEIf+Jobr2o1Yl1+ZakWZ+parsTgnodyWl432Hsv2FUNLhuU7H6folMwleaZFPYdFZ+bO1T95opw5pnDWcFkrIuPfAmVRg4cg+al22FQSN/58AXxWBb8jEPrqn+/ojZ+WqncGvw+NB/Mtv9iCDuF+SNQqRig2dRILzWYwcvNxzj/OxcYuNuvO8wYI/iF1kNBBNtaExIunWZyj1tntGeb7UUaDmHD4LmSMUMpgZGugRfUpxm8WL/EE+PnUkLXE7SOG3g==',
                    ],
                ],
            ],
        ];
    }

    public static function threeDFormDataProviderFail(): array
    {
        return [
            '3d_host_form_data'           => [
                'order'              => [],
                'tx_type'            => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model'      => PosInterface::MODEL_3D_HOST,
                'gateway'            => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                'extra_data'         => [
                    'TO_Pre_Encrypting_OOSResponse' => [
                        'TO_Pre_Encrypting_OOSResult' => 'SOAP Güvenlik Hatası.192.168.190.2',
                    ],
                ],
                'expected_exception' => \RuntimeException::class,
            ],
            'non_3d_host_form_data'       => [
                'order'              => [],
                'tx_type'            => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model'      => PosInterface::MODEL_3D_SECURE,
                'gateway'            => 'https://test-pos.param.com.tr/to.ws/Service_Odeme.asmx',
                'extra_data'         => [
                    'TO_Pre_Encrypting_OOSResponse' => [
                        'TO_Pre_Encrypting_OOSResult' => 'SOAP Güvenlik Hatası.192.168.190.2',
                    ],
                ],
                'expected_exception' => \InvalidArgumentException::class,
            ],
            '3d_host_without_gateway_url' => [
                'order'              => [],
                'tx_type'            => PosInterface::TX_TYPE_PAY_AUTH,
                'payment_model'      => PosInterface::MODEL_3D_HOST,
                'gateway'            => null,
                'extra_data'         => [
                    'TO_Pre_Encrypting_OOSResponse' => [
                        'TO_Pre_Encrypting_OOSResult' => 'SOAP Güvenlik Hatası.192.168.190.2',
                    ],
                ],
                'expected_exception' => \InvalidArgumentException::class,
            ],
        ];
    }
}
