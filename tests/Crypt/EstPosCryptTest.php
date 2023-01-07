<?php

namespace Mews\Pos\Tests\Crypt;

use Mews\Pos\Crypt\EstPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\AbstractGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EstPosCryptTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $threeDAccount;

    /** @var EstPosCrypt */
    private $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->threeDAccount = AccountFactory::createEstPosAccount(
            'akbank',
            '700655000200',
            'ISBANKAPI',
            'ISBANK07',
            AbstractGateway::MODEL_3D_SECURE,
            'TRPS0200'
        );

        $this->crypt = new EstPosCrypt(new NullLogger());
    }

    public function testCreate3DHash()
    {
        $this->threeDAccount = AccountFactory::createEstPosAccount(
            'akbank',
            '700655000200',
            'ISBANKAPI',
            'ISBANK07',
            AbstractGateway::MODEL_3D_SECURE,
            'TRPS0200'
        );

        $order = [
            'id'          => 'order222',
            'amount'      => '100.25',
            'installment' => '',
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'rand'        => 'rand',
        ];
        $expected = 'S7UxUAohxaxzl35WxHyDfuQx0sg=';

        $actual = $this->crypt->create3DHash($this->threeDAccount, $order, 'Auth');
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testCreate3DHashFor3DPay()
    {
        $requestData = [
            'id' => 'order222',
            'amount' => '100.25',
            'installment' => '',
            'currency' => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url' => 'https://domain.com/fail_url',
            'rand' => 'rand',
        ];
        $expected = 'S7UxUAohxaxzl35WxHyDfuQx0sg=';

        $actual = $this->crypt->create3DHash($this->threeDAccount, $requestData, 'Auth');
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(bool $expected, array $responseData)
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->threeDAccount, $responseData));

        $responseData['mdStatus'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->threeDAccount, $responseData));
    }

    public function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => [
                    'TRANID'                          => '',
                    'PAResSyntaxOK'                   => 'true',
                    'firmaadi'                        => 'John Doe',
                    'lang'                            => 'tr',
                    'merchantID'                      => '700655000200',
                    'maskedCreditCard'                => '4355 08** **** 4358',
                    'amount'                          => '1.01',
                    'sID'                             => '1',
                    'ACQBIN'                          => '406456',
                    'Ecom_Payment_Card_ExpDate_Year'  => '30',
                    'Email'                           => 'mail@customer.com',
                    'MaskedPan'                       => '435508***4358',
                    'clientIp'                        => '89.244.149.137',
                    'iReqDetail'                      => '',
                    'okUrl'                           => 'http://localhost/akbank/3d/response.php',
                    'md'                              => '435508:86D9842A9C594E17B28A2B9037FEB140E8EA480AED5FE19B5CEA446960AA03AA:4122:##700655000200',
                    'vendorCode'                      => '',
                    'Ecom_Payment_Card_ExpDate_Month' => '12',
                    'storetype'                       => '3d',
                    'iReqCode'                        => '',
                    'mdErrorMsg'                      => 'Not authenticated',
                    'PAResVerified'                   => 'false',
                    'cavv'                            => '',
                    'digest'                          => 'digest',
                    'callbackCall'                    => 'true',
                    'failUrl'                         => 'http://localhost/akbank/3d/response.php',
                    'cavvAlgorithm'                   => '',
                    'xid'                             => 'FKqfXqwd0VA5RILtjmwaW17t/jk=',
                    'encoding'                        => 'ISO-8859-9',
                    'currency'                        => '949',
                    'oid'                             => '202204171C44',
                    'mdStatus'                        => '0',
                    'dsId'                            => '1',
                    'eci'                             => '',
                    'version'                         => '2.0',
                    'clientid'                        => '700655000200',
                    'txstatus'                        => 'N',
                    '_charset_'                       => 'UTF-8',
                    'HASH'                            => 'e5KcIY797JNvjrkWjZSfHOa+690=',
                    'rnd'                             => 'mzTLQAaM8W5GuQwu4BfD',
                    'HASHPARAMS'                      => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
                    'HASHPARAMSVAL'                   => '700655000200202204171C440435508:86D9842A9C594E17B28A2B9037FEB140E8EA480AED5FE19B5CEA446960AA03AA:4122:##700655000200mzTLQAaM8W5GuQwu4BfD',
                ],
            ],
        ];
    }
}
