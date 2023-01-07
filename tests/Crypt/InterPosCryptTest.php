<?php

namespace Mews\Pos\Tests\Crypt;

use Mews\Pos\Crypt\InterPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\AbstractGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class InterPosCryptTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $threeDAccount;

    /** @var InterPosCrypt */
    private $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $userCode     = 'InterTestApi';
        $userPass     = '3';
        $shopCode     = '3123';
        $merchantPass = 'gDg1N';

        $this->threeDAccount = AccountFactory::createInterPosAccount(
            'denizbank',
            $shopCode,
            $userCode,
            $userPass,
            AbstractGateway::MODEL_3D_SECURE,
            $merchantPass
        );

        $this->crypt = new InterPosCrypt(new NullLogger());
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(array $responseData)
    {
        $this->assertTrue($this->crypt->check3DHash($this->threeDAccount, $responseData));
    }

    /**
     * @dataProvider threeDHashCreateDataProvider
     */
    public function testCreate3DHash(array $requestData, string $txType, string $expected)
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

        $actual = $this->crypt->create3DHash($this->threeDAccount, $requestData, $txType);

        $this->assertSame($expected, $actual);
    }

    public function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'responseData'   => [
                    'Version'        => '',
                    'PurchAmount'    => 320,
                    'Exponent'       => '',
                    'Currency'       => '949',
                    'OkUrl'          => 'https://localhost/pos/examples/interpos/3d/success.php',
                    'FailUrl'        => 'https://localhost/pos/examples/interpos/3d/fail.php',
                    'MD'             => '',
                    'OrderId'        => '20220327140D',
                    'ProcReturnCode' => '81',
                    'Response'       => '',
                    'mdStatus'       => '0',
                    'HASH'           => '9DZVckklZFjuoA7sl4MN0l7VDMo=',
                    'HASHPARAMS'     => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
                    'HASHPARAMSVAL'  => '320949https://localhost/pos/examples/interpos/3d/success.phphttps://localhost/pos/examples/interpos/3d/fail.php20220327140D810',
                ],
            ],
            [
                'responseData' => [
                    'Version'                 => null,
                    'ShopCode'                => '3123',
                    'MD'                      => null,
                    'OrderId'                 => '20221225E1DF',
                    'PurchAmount'             => '1,01',
                    'Exponent'                => null,
                    'Currency'                => '949',
                    'OkUrl'                   => 'http:\/\/localhost\/interpos\/3d\/response.php',
                    'FailUrl'                 => 'http:\/\/localhost\/interpos\/3d\/response.php',
                    '3DStatus'                => '0',
                    'mdStatus'                => '0',
                    'ProcReturnCode'          => '81',
                    'Response'                => null,
                    'HASH'                    => '423AWRAXl0VlEbQjpmAfntT5e3E=',
                    'HASHPARAMS'              => 'Version:PurchAmount:Exponent:Currency:OkUrl:FailUrl:MD:OrderId:ProcReturnCode:Response:mdStatus:',
                    'HASHPARAMSVAL'           => '1,01949http:\/\/localhost\/interpos\/3d\/response.phphttp:\/\/localhost\/interpos\/3d\/response.php20221225E1DF810',
                ],
            ],
        ];
    }

    public function threeDHashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id' => 'order222',
                    'amount' => '100.25',
                    'installment' => '',
                    'currency' => 'TRY',
                    'success_url' => 'https://domain.com/success',
                    'fail_url' => 'https://domain.com/fail_url',
                    'lang' => 'tr',
                    'rand' => 'rand',
                ],
                'txType' => 'Auth',
                'expected' => 'vEbwP8wnsGrBR9oCjfxP9wlho1g=',
            ],
        ];
    }
}
