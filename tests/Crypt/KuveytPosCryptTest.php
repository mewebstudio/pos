<?php

namespace Mews\Pos\Tests\Crypt;

use Mews\Pos\Crypt\KuveytPosCrypt;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class KuveytPosCryptTest extends TestCase
{
    /** @var KuveytPosCrypt */
    public $crypt;

    /** @var KuveytPosAccount */
    private $account;

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


    /**
     * @dataProvider threeDHashCreateDataProvider
     */
    public function testCreate3DHash(array $requestData, string $expected)
    {
        $actual = $this->crypt->create3DHash($this->account, $requestData);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider hashCreateDataProvider
     */
    public function testCreateHash(array $requestData, string $expected)
    {
        $actual = $this->crypt->createHash($this->account, $requestData);

        $this->assertSame($expected, $actual);
    }

    public function hashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id'          => 'ORDER-123',
                    'amount'      => 7256,
                    'currency'    => PosInterface::CURRENCY_TRY,
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
