<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\InterPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Crypt\InterPosCrypt
 * @covers \Mews\Pos\Crypt\AbstractCrypt
 */
class InterPosCryptTest extends TestCase
{
    private InterPosAccount $account;

    private InterPosCrypt $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $userCode     = 'InterTestApi';
        $userPass     = '3';
        $shopCode     = '3123';
        $merchantPass = 'gDg1N';

        $this->account = AccountFactory::createInterPosAccount(
            'denizbank',
            $shopCode,
            $userCode,
            $userPass,
            PosInterface::MODEL_3D_SECURE,
            $merchantPass
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new InterPosCrypt($logger);
    }

    public function testSupports(): void
    {
        $supports = $this->crypt::supports(InterPos::class);
        $this->assertTrue($supports);

        $supports = $this->crypt::supports(EstV3Pos::class);
        $this->assertFalse($supports);
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(array $responseData): void
    {
        $this->assertTrue($this->crypt->check3DHash($this->account, $responseData));

        $responseData['PurchAmount'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->account, $responseData));
    }

    /**
     * @dataProvider threeDHashCreateDataProvider
     */
    public function testCreate3DHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->create3DHash($this->account, $requestData);

        $this->assertSame($expected, $actual);
    }

    public function testCreateHash(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->crypt->createHash($this->account, []);
    }

    public function testCheck3DHashException(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(\LogicException::class);
        $this->crypt->check3DHash($account, []);
    }

    public static function threeDHashCheckDataProvider(): array
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

    public static function threeDHashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'ShopCode'         => '3123',
                    'OrderId'          => 'order222',
                    'PurchAmount'      => '100.25',
                    'TxnType'          => 'Auth',
                    'InstallmentCount' => '',
                    'OkUrl'            => 'https://domain.com/success',
                    'FailUrl'          => 'https://domain.com/fail_url',
                    'Rnd'              => 'rand',
                ],
                'expected'    => 'vEbwP8wnsGrBR9oCjfxP9wlho1g=',
            ],
        ];
    }
}
