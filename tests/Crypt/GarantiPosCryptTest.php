<?php

namespace Mews\Pos\Tests\Crypt;

use Mews\Pos\Crypt\GarantiPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GarantiPosCryptTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $account;

    /** @var GarantiPosCrypt */
    private $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            PosInterface::MODEL_3D_SECURE,
            '12345678',
            'PROVRFN',
            '123qweASD/'
        );

        $this->crypt = new GarantiPosCrypt(new NullLogger());
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(bool $expected, array $responseData)
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->account, $responseData));

        $responseData['mdstatus'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->account, $responseData));
    }

    /**
     * @return void
     */
    public function testCreate3DHash()
    {
        $requestData = [
              'orderid' => 'order222',
              'txnamount' => 10025,
              'txninstallmentcount' => '',
              'txntype' => 'sales',
              'successurl' => 'https://domain.com/success',
              'errorurl' => 'https://domain.com/fail_url',
        ];

        $expected = '1D319D5EA945F5730FF5BCC970FF96690993F4BD';
        $actual = $this->crypt->create3DHash($this->account, $requestData);
        $this->assertSame($expected, $actual);
    }


    /**
     * @dataProvider hashCreateDataProvider
     */
    public function testCreateHash(array $requestData, string $expected)
    {
        $actual = $this->crypt->createHash($this->account, $requestData);
        $this->assertEquals($expected, $actual);
    }

    public function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => [
                    'mdstatus' => '1',
                    'eci' => '02',
                    'cavv' => 'jCm0m+u/0hUfAREHBAMBcfN+pSo=',
                    'version' => '2.0',
                    'md' => 'G1YfkxEZ8Noemg4MRspO20vEiXaEk51A7ajPU4mKMSVmw34xpTV+8XSfhPw2vMA2XLuPjeXSvG00zCA+78yYbp83WAhjLy/qNPUCphGiC1db8oGIphWRRimqxZi1iCq3rYtLbz6EoZRFU18UGFUotA==',
                    'oid' => '20221101ACC4',
                    'authcode' => '',
                    'response' => '',
                    'procreturncode' => '',
                    'rnd' => '3tfMjkdGsXQNc11PxchJ',
                    'hash' => 'r2KD+NS0f82pwRPekR1BTIT1mik=',
                    'hashparams' => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
                    'hashparamsval' => '3069129820221101ACC41jCm0m+u/0hUfAREHBAMBcfN+pSo=02G1YfkxEZ8Noemg4MRspO20vEiXaEk51A7ajPU4mKMSVmw34xpTV+8XSfhPw2vMA2XLuPjeXSvG00zCA+78yYbp83WAhjLy/qNPUCphGiC1db8oGIphWRRimqxZi1iCq3rYtLbz6EoZRFU18UGFUotA==3tfMjkdGsXQNc11PxchJ',
                    'clientid' => '30691298',
                ],
            ],
        ];
    }

    public function hashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'Order'       => [
                        'OrderID' => 'order222',
                    ],
                    'Transaction' => [
                        'Type'   => 'sales',
                        'Amount' => 10025,
                    ],
                ],
                'expected' => '00CD5B6C29D4CEA1F3002D785A9F9B09974AD51D',
            ],
            [
                'requestData' => [
                    'Order'       => [
                        'OrderID' => 'order222',
                    ],
                    'Transaction' => [
                        'Type'   => 'preauth',
                        'Amount' => 10025,
                    ],
                ],
                'expected' => '00CD5B6C29D4CEA1F3002D785A9F9B09974AD51D',
            ],
            [
                'requestData' => [
                    'Order'       => [
                        'OrderID' => '4499996',
                    ],
                    'Transaction' => [
                        'Type'   => 'void',
                        // for cancel request amount is always 100
                        'Amount' => 100,
                    ],
                ],
                'expected' => '9788649A0C3AE14C082783CEA6775E08A7EFB311',
            ],
            [
                'requestData' => [
                    'Order'       => [
                        'OrderID' => '4499996',
                    ],
                    'Transaction' => [
                        'Type'   => 'refund',
                        'Amount' => 202,
                    ],
                ],
                'expected' => 'D7094EAF4C444AAC429FB2424BEE7FC68470E0DE',
            ],
        ];
    }
}
