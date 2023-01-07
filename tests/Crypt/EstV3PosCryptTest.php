<?php

namespace Mews\Pos\Tests\Crypt;

use Mews\Pos\Crypt\EstV3PosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\AbstractGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EstV3PosCryptTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $threeDAccount;

    /** @var EstV3PosCrypt */
    private $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->threeDAccount = AccountFactory::createEstPosAccount(
            'akbank',
            '190100000',
            'ISBANKAPI',
            'ISBANK07',
            AbstractGateway::MODEL_3D_SECURE,
            '123456'
        );

        $this->crypt = new EstV3PosCrypt(new NullLogger());
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(array $responseData)
    {
        $this->assertTrue($this->crypt->check3DHash($this->threeDAccount, $responseData));

        $responseData['mdStatus'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->threeDAccount, $responseData));
    }

    public function testCreate3DHashFor3DPay()
    {
        $expected = '/uPxhEKWCEGi3NsDOOQ4u8Hu5g71v5GWspOid70WehTWEz97PqxG3IN1Jv5jsbOXOw3Z3Rr/0UtywzEgbqFfdA==';

        $requestData = [
            'clientid'      => $this->threeDAccount->getClientId(),
            'storetype'     => '3d_pay',
            'amount'        => '100.25',
            'oid'           => 'order222',
            'okUrl'         => 'https://domain.com/success',
            'failUrl'       => 'https://domain.com/fail_url',
            'rnd'           => 'rand',
            'lang'          => 'tr',
            'currency'      => '949',
            'taksit'        => '',
            'islemtipi'     => 'Auth',
            'firmaadi'      => 'siparis veren',
            'Email'         => 'test@test.com',
            'hashAlgorithm' => 'ver3',
        ];

        $actual = $this->crypt->create3DHash($this->threeDAccount, $requestData, AbstractGateway::TX_PAY);
        $this->assertEquals($expected, $actual);
    }

    public function testCreate3DHashFor3DSecure()
    {
        $account = $this->threeDAccount;
        $inputs  = [
            'clientid'      => $account->getClientId(),
            'storetype'     => $account->getModel(),
            'amount'        => '100.25',
            'oid'           => 'order222',
            'okUrl'         => 'https://domain.com/success',
            'failUrl'       => 'https://domain.com/fail_url',
            'rnd'           => 12345,
            'hashAlgorithm' => 'ver3',
            'lang'          => 'tr',
            'currency'      => 949,
            'islemtipi'     => 'Auth',
            'taksit'        => '',
        ];

        $expected = '4aUsG5hqlIFLc9s8PKc5rWb2OLhmxDDewNgKa2XrwoYCIxlyVq8Fjl4IVaZzoqL983CfTseicmnTA0PjZr74xg==';
        $actual   = $this->crypt->create3DHash($this->threeDAccount, $inputs, AbstractGateway::TX_PAY);
        $this->assertEquals($expected, $actual);
    }

    public function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'responseData' => [
                    'ReturnOid'                       => '202210308C0F',
                    'TRANID'                          => null,
                    'hashAlgorithm'                   => 'ver3',
                    'PAResSyntaxOK'                   => 'true',
                    'firmaadi'                        => 'John Doe',
                    'islemtipi'                       => 'Auth',
                    'lang'                            => 'tr',
                    'merchantID'                      => '190200000',
                    'maskedCreditCard'                => '4355 08** **** 4358',
                    'amount'                          => '1.01',
                    'sID'                             => '1',
                    'ACQBIN'                          => '454672',
                    'Ecom_Payment_Card_ExpDate_Year'  => '26',
                    'EXTRA_CARDBRAND'                 => 'VISA',
                    'Email'                           => 'mail@customer.com',
                    'MaskedPan'                       => '435508***4358',
                    'acqStan'                         => '184784',
                    'merchantName'                    => 'Ziraat 3D_Pay',
                    'clientIp'                        => '89.244.149.137',
                    'EXTRA_KAZANILANPUAN'             => '000000010.00',
                    'okUrl'                           => 'http://localhost/akbank/3d-pay/response.php',
                    'md'                              => '435508:51104213993AC8DFE8839C7F7E391B461115D09A1D88948C8ACE421D7D7EAA5A:3545:##190200000',
                    'ProcReturnCode'                  => '00',
                    'payResults_dsId'                 => '1',
                    'taksit'                          => null,
                    'TransId'                         => '22303RqiC11252',
                    'EXTRA_TRXDATE'                   => '20221030 17:42:33',
                    'Ecom_Payment_Card_ExpDate_Month' => '12',
                    'storetype'                       => '3d_pay',
                    'Response'                        => 'Approved',
                    'SettleId'                        => '2383',
                    'mdErrorMsg'                      => 'Y-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/creq?token=214711321.1667140951.Ud_jQl3B2l1',
                    'ErrMsg'                          => null,
                    'PAResVerified'                   => 'true',
                    'cavv'                            => 'ABABBCgghAAAAABllJMDdZdTdic=',
                    'digest'                          => 'digest',
                    'HostRefNum'                      => '230317184784',
                    'callbackCall'                    => 'true',
                    'AuthCode'                        => '216981',
                    'failUrl'                         => 'http://localhost/akbank/3d-pay/response.php',
                    'xid'                             => 'N1An1iS5iN4u/1VWS6CgzSGRtN0=',
                    'encoding'                        => 'ISO-8859-9',
                    'currency'                        => '949',
                    'oid'                             => '202210308C0F',
                    'mdStatus'                        => '1',
                    'dsId'                            => '1',
                    'eci'                             => '05',
                    'version'                         => '2.0',
                    'EXTRA_CARDISSUER'                => 'AKBANK T.A.S.',
                    'clientid'                        => '190200000',
                    'txstatus'                        => 'Y',
                    'HASH'                            => 'jVgcwtx4vxxAcX8+ajsrrzp77k9uaGu1pjDQfCpoUG95y2MwwGipc6cF3kESiTphjV8/1ESSc6zg9KyTSxUnzA==',
                    'rnd'                             => 'J/jGATA5LLVaMfpU+Yyu',
                ],
            ],
        ];
    }

}
