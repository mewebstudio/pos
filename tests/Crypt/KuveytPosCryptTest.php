<?php

namespace Mews\Pos\Tests\Crypt;

use Mews\Pos\Crypt\KuveytPosCrypt;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\AbstractGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class KuveytPosCryptTest extends TestCase
{
    /**
     * @var KuveytPosAccount
     */
    private $threeDAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->threeDAccount = AccountFactory::createKuveytPosAccount(
            'kuveytpos',
            '80',
            'apiuser',
            '400235',
            'Api123'
        );

        $this->order = [
            'id'          => '2020110828BC',
            'amount'      => 10.01,
            'installment' => '0',
            'currency'    => 'TRY',
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'rand'        => '0.43625700 1604831630',
            'hash'        => 'zmSUxYPhmCj7QOzqpk/28LuE1Oc=',
            'ip'          => '127.0.0.1',
            'lang'        => AbstractGateway::LANG_TR,
        ];

        $this->crypt = new KuveytPosCrypt(new NullLogger());
    }


    /**
     * @dataProvider threeDHashCreateDataProvider
     */
    public function testCreate3DHash(array $requestData, string $expected)
    {
        $actual = $this->crypt->create3DHash($this->threeDAccount, $requestData);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider hashCreateDataProvider
     */
    public function testCreateHash(array $requestData, string $expected)
    {
        $actual = $this->crypt->createHash($this->threeDAccount, $requestData);

        $this->assertSame($expected, $actual);
    }

    public function hashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id'          => 'ORDER-123',
                    'amount'      => 7256,
                    'currency'    => 'TRY',
                    'installment' => '0',
                ],
                'expected'    => 'Bf+hZf2c1gf1pTXnEaSGxDpGRr0=',
            ],
        ];
    }

    public function threeDHashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id'          => 'ORDER-123',
                    'amount'      => 7256,
                    'currency'    => 'TRY',
                    'success_url' => 'http://localhost:44785/Home/Success',
                    'fail_url'    => 'http://localhost:44785/Home/Fail',
                ],
                'expected'    => 'P3a0zjAklu2g8XDJfTx2qvwHH8g=',
            ],
        ];
    }
}
