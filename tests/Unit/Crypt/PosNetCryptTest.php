<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\PosNetCrypt;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Crypt\PosNetCrypt
 * @covers \Mews\Pos\Crypt\AbstractCrypt
 */
class PosNetCryptTest extends TestCase
{
    public PosNetCrypt $crypt;

    private PosNetAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPosNetAccount(
            'yapikredi',
            '6706598320',
            '67005551',
            '27426',
            PosInterface::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new PosNetCrypt($logger);
    }


    /**
     * @dataProvider threeDHashCreateDataProvider
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

        $responseData['amount'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->account, $responseData));
    }

    /**
     * @return void
     */
    public function testCreateSecurityData(): void
    {
        $this->assertSame('c1PPl+2UcdixyhgLYnf4VfJyFGaNQNOwE0uMkci7Uag=', $this->crypt->createSecurityData($this->account));
    }

    public function threeDHashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id' => 'TST_190620093100_024',
                    'amount' => 175,
                      'installment' => 0,
                      'currency' => 'TL',
                      'success_url' => 'https://domain.com/success',
                      'fail_url' => 'https://domain.com/fail_url',
                      'rand' => '0.43625700 1604831630',
                      'lang' => 'tr',

                ],
                'expected'    => 'nyeFSQ4J9NZVeCcEGCDomM8e2YIvoeIa/IDh2D3qaL4=',
            ],
        ];
    }

    public function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => [
                    'xid' => '00000000000000000895',
                    'amount' => '100',
                    'currency' => 'TL',
                    'installment' => '00',
                    'point' => '0',
                    'pointAmount' => '0',
                    'txStatus' => 'N',
                    'mdStatus' => '9',
                    'mdErrorMessage' => 'None 3D - Secure Transaction',
                    'mac'        => '7I9ojRm7yzvZZTFNOYWocGIGSTv2Vmq23STR6X6X+c0=',
                ],
            ],
        ];
    }

}
