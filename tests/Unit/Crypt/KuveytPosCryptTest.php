<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\KuveytPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\Crypt\KuveytPosCrypt
 */
class KuveytPosCryptTest extends TestCase
{
    public KuveytPosCrypt $crypt;

    private KuveytPosAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createKuveytPosAccount(
            'kuveytpos',
            '80',
            'apiuser',
            '400235',
            'Api123'
        );

        $this->crypt = new KuveytPosCrypt(new NullLogger());
    }

    public function testHashString(): void
    {
        $actual = $this->crypt->hashString('123');

        $this->assertSame('QL0AFWMIX8NRZTKeof9cXsvbvu8=', $actual);
    }

    /**
     * @dataProvider threeDHashCreateDataProvider
     */
    public function testCreate3DHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->create3DHash($this->account, $requestData);

        $this->assertSame($expected, $actual);
    }

    public function testCheck3DHash(): void
    {
        $this->expectException(NotImplementedException::class);

        $this->crypt->check3DHash($this->account, []);
    }

    /**
     * @dataProvider hashCreateDataProvider
     */
    public function testCreateHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->createHash($this->account, $requestData);

        $this->assertSame($expected, $actual);
    }

    public function testCreateHashException(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(\LogicException::class);
        $this->crypt->createHash($account, []);
    }

    public static function hashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'MerchantOrderId' => 'ORDER-123',
                    'Amount'          => 7256,
                ],
                'expected'    => 'Bf+hZf2c1gf1pTXnEaSGxDpGRr0=',
            ],
        ];
    }

    public static function threeDHashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'MerchantOrderId' => 'ORDER-123',
                    'Amount'          => 7256,
                    'OkUrl'           => 'http://localhost:44785/Home/Success',
                    'FailUrl'         => 'http://localhost:44785/Home/Fail',
                ],
                'expected'    => 'P3a0zjAklu2g8XDJfTx2qvwHH8g=',
            ],
        ];
    }
}
