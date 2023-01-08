<?php

namespace Mews\Pos\Tests\Crypt;

use Mews\Pos\Crypt\GarantiPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\AbstractGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GarantiPosCryptTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $threeDAccount;

    /** @var GarantiPosCrypt */
    private $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->threeDAccount = AccountFactory::createGarantiPosAccount(
            'garanti',
            '7000679',
            'PROVAUT',
            '123qweASD/',
            '30691298',
            AbstractGateway::MODEL_3D_SECURE,
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
        $this->assertSame($expected, $this->crypt->check3DHash($this->threeDAccount, $responseData));

        $responseData['mdstatus'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->threeDAccount, $responseData));
    }

    /**
     * @return void
     */
    public function testCreate3DHash()
    {
        $requestData = [
              'id' => 'order222',
              'amount' => 10025,
              'installment' => '',
              'currency' => '949',
              'success_url' => 'https://domain.com/success',
              'fail_url' => 'https://domain.com/fail_url',
        ];

        $expected = '1D319D5EA945F5730FF5BCC970FF96690993F4BD';
        $actual = $this->crypt->create3DHash($this->threeDAccount, $requestData, 'sales');
        $this->assertSame($expected, $actual);
    }


    /**
     * @dataProvider hashCreateDataProvider
     */
    public function testCreateHash(array $requestData, string $txType, string $expected)
    {
        $actual = $this->crypt->createHash($this->threeDAccount, $requestData, $txType);
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
                    'id' => 'order222',
                    'amount' => 10025,
                ],
                'txType' => 'sales',
                'expected' => '00CD5B6C29D4CEA1F3002D785A9F9B09974AD51D',
            ],
            [
                'requestData' => [
                    'id' => 'order222',
                    'amount' => 10025,
                ],
                'txType' => 'preauth',
                'expected' => '00CD5B6C29D4CEA1F3002D785A9F9B09974AD51D',
            ],
            [
                'requestData' => [
                    'id' => '4499996',
                    // for cancel request amount is always 100
                    'amount' => 100,
                ],
                'txType' => 'void',
                'expected' => '9788649A0C3AE14C082783CEA6775E08A7EFB311',
            ],
            [
                'requestData' => [
                    'id' => '4499996',
                    'amount' => 202,
                ],
                'txType' => 'refund',
                'expected' => 'D7094EAF4C444AAC429FB2424BEE7FC68470E0DE',
            ],
        ];
    }
}
