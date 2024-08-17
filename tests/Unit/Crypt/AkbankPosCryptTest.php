<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\AkbankPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\AkbankPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\AkbankPosResponseDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\Crypt\AkbankPosCrypt
 * @covers \Mews\Pos\Crypt\AbstractCrypt
 */
class AkbankPosCryptTest extends TestCase
{
    private AkbankPosAccount $account;

    private AkbankPosCrypt $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createAkbankPosAccount(
            'akbank',
            '2023090417500272654BD9A49CF07574',
            '2023090417500284633D137A249DBBEB',
            '3230323330393034313735303032363031353172675f357637355f3273387373745f7233725f73323333383737335f323272383774767276327672323531355f',
            PosInterface::LANG_TR,
            'sub-2023090417500272654BD9A49CF07574'
        );

        $this->crypt = new AkbankPosCrypt(new NullLogger());
    }

    public function testGenerateRandomString(): void
    {
        $str = $this->crypt->generateRandomString();
        $this->assertSame(128, strlen($str));
    }

    public function testCreateHash(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->crypt->createHash($this->account, []);
    }

    /**
     * @dataProvider hashStringDataProvider
     */
    public function testHashString(string $str, string $expected): void
    {
        $actual = $this->crypt->hashString($str, $this->account->getStoreKey());
        $this->assertSame($expected, $actual);
    }

    public function testHashStringException(): void
    {
        $this->expectException(\LogicException::class);
        $this->crypt->hashString('abc');
    }

    /**
     * @dataProvider create3DHashDataProvider
     */
    public function testCreate3DHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->create3DHash($this->account, $requestData);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(bool $expected, array $responseData): void
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->account, $responseData));

        $responseData['responseCode'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->account, $responseData));
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHashException(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(\LogicException::class);
        $this->crypt->check3DHash($account, []);
    }


    public function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => AkbankPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData'],
            ],
            [
                'expectedResult' => true,
                'responseData'   => AkbankPosResponseDataMapperTest::threeDPayPaymentDataProvider()['success1']['paymentData'],
            ],
            [
                'expectedResult' => true,
                'responseData'   => AkbankPosResponseDataMapperTest::threeDHostPaymentDataProvider()['success1']['paymentData'],
            ],
        ];
    }

    public static function create3DHashDataProvider(): array
    {
        return [
            '3d_secure' => [
                'requestData' => [
                    'paymentModel'    => '3D',
                    'txnCode'         => '3000',
                    'merchantSafeId'  => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId'  => '2023090417500284633D137A249DBBEB',
                    'orderId'         => '20240404A4B0',
                    'lang'            => 'TR',
                    'amount'          => '1.01',
                    'ccbRewardAmount' => '1.00',
                    'pcbRewardAmount' => '1.00',
                    'xcbRewardAmount' => '1.00',
                    'currencyCode'    => '949',
                    'installCount'    => '1',
                    'okUrl'           => 'http://localhost/akbankpos/3d/response.php',
                    'failUrl'         => 'http://localhost/akbankpos/3d/response.php',
                    'subMerchantId'   => null,
                    'emailAddress'    => 'test@test.com',
                    'randomNumber'    => 'AEDDD8688E11A3DC588DAB2ED59B2F64D45E798761CEFF17F4DB47581072697890180C4195986250F89C2C67A04A3B96F0AC66AE99B49BB7BEE618FBD621C4CD',
                    'requestDateTime' => '2024-04-04T21:11:41.000',
                    'creditCard'      => '4355093000315232',
                    'expiredDate'     => '1135',
                    'cvv'             => '665',
                ],
                'expected'    => 'N0iWnWjG93R20newWV7hJgpPaKmt2W4TwMP9BdvKSwcFVtJF+3JRlP7cxAcQMFe1XKQJNd1rYO4jsX65zrKUDg==',
            ],
            '3d_host'   => [
                'requestData' => [
                    'paymentModel'    => 'MODEL_3D_HOST',
                    'txnCode'         => '3000',
                    'merchantSafeId'  => '2023090417500272654BD9A49CF07574',
                    'terminalSafeId'  => '2023090417500284633D137A249DBBEB',
                    'orderId'         => '20240404A4B0',
                    'lang'            => 'TR',
                    'amount'          => '1.01',
                    'ccbRewardAmount' => '1.00',
                    'pcbRewardAmount' => '1.00',
                    'xcbRewardAmount' => '1.00',
                    'currencyCode'    => '949',
                    'installCount'    => '1',
                    'okUrl'           => 'http://localhost/akbankpos/3d/response.php',
                    'failUrl'         => 'http://localhost/akbankpos/3d/response.php',
                    'subMerchantId'   => null,
                    'emailAddress'    => 'test@test.com',
                    'randomNumber'    => 'AEDDD8688E11A3DC588DAB2ED59B2F64D45E798761CEFF17F4DB47581072697890180C4195986250F89C2C67A04A3B96F0AC66AE99B49BB7BEE618FBD621C4CD',
                    'requestDateTime' => '2024-04-04T21:11:41.000',
                ],
                'expected'    => '8dWHFFeUZ17Q5uEPKtIxJBDCTBJ11aPQpYdpsIISSjSUPRbXrax35kLVpqK4Hv6YpXFfA7ltqrp/yv7WwenvxQ==',
            ],
        ];
    }

    public static function hashStringDataProvider(): array
    {
        return [
            [
                'string' => '{"terminal":{"merchantSafeId":"2023090417500272654BD9A49CF07574","terminalSafeId":"2023090417500284633D137A249DBBEB"},"version":"1.00","txnCode":"1010","requestDateTime":"2024-04-20T13:48:02.000","randomNumber":"59EED19EC4FA761B8D147F5175C915EFD69D193ED96114F9690505EDB02FF5FD3CB161A15FD5EFFB294177291DC27B2A9F58FB1DA6F2F4617762AF180A023A33","order":{"orderId":"2024042053E2"}}',
                'expected' => 'D07V9wUXfyPbn+L8jqd9plMfJKT3gSy22Z+yruzWsvuJBkQ63fz+wcWjTF2XurVjYtnR7wLGYTJYg0xjDCYAHA==',
            ],
        ];
    }
}
