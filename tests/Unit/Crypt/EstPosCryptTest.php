<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\EstPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Crypt\EstPosCrypt
 * @covers \Mews\Pos\Crypt\AbstractCrypt
 */
class EstPosCryptTest extends TestCase
{
    private EstPosAccount $account;

    private EstPosCrypt $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createEstPosAccount(
            'akbank',
            '700655000200',
            'ISBANKAPI',
            'ISBANK07',
            PosInterface::MODEL_3D_SECURE,
            'TRPS0200'
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new EstPosCrypt($logger);
    }

    public function testCreate3DHash(): void
    {
        $requestData = [
            'clientid'  => '700655000200',
            'oid'       => 'order222',
            'amount'    => '100.25',
            'taksit'    => '',
            'islemtipi' => 'Auth',
            'okUrl'     => 'https://domain.com/success',
            'failUrl'   => 'https://domain.com/fail_url',
            'rnd'       => 'rand',
        ];
        $expected = 'S7UxUAohxaxzl35WxHyDfuQx0sg=';

        $actual = $this->crypt->create3DHash($this->account, $requestData);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(bool $expected, array $responseData): void
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->account, $responseData));

        $responseData['mdStatus'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->account, $responseData));
    }

    public function testCheck3DHashException(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(\LogicException::class);
        $this->crypt->check3DHash($account, []);
    }

    public function testCreateHash(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->crypt->createHash($this->account, []);
    }

    public static function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => [
                    'TRANID'                          => '',
                    'PAResSyntaxOK'                   => 'true',
                    'firmaadi'                        => 'John Doe',
                    'lang'                            => 'tr',
                    'merchantID'                      => '700655000200',
                    'maskedCreditCard'                => '4355 08** **** 4358',
                    'amount'                          => '1.01',
                    'sID'                             => '1',
                    'ACQBIN'                          => '406456',
                    'Ecom_Payment_Card_ExpDate_Year'  => '30',
                    'MaskedPan'                       => '435508***4358',
                    'clientIp'                        => '89.244.149.137',
                    'iReqDetail'                      => '',
                    'okUrl'                           => 'http://localhost/akbank/3d/response.php',
                    'md'                              => '435508:86D9842A9C594E17B28A2B9037FEB140E8EA480AED5FE19B5CEA446960AA03AA:4122:##700655000200',
                    'vendorCode'                      => '',
                    'Ecom_Payment_Card_ExpDate_Month' => '12',
                    'storetype'                       => '3d',
                    'iReqCode'                        => '',
                    'mdErrorMsg'                      => 'Not authenticated',
                    'PAResVerified'                   => 'false',
                    'cavv'                            => '',
                    'digest'                          => 'digest',
                    'callbackCall'                    => 'true',
                    'failUrl'                         => 'http://localhost/akbank/3d/response.php',
                    'cavvAlgorithm'                   => '',
                    'xid'                             => 'FKqfXqwd0VA5RILtjmwaW17t/jk=',
                    'encoding'                        => 'ISO-8859-9',
                    'currency'                        => '949',
                    'oid'                             => '202204171C44',
                    'mdStatus'                        => '0',
                    'dsId'                            => '1',
                    'eci'                             => '',
                    'version'                         => '2.0',
                    'clientid'                        => '700655000200',
                    'txstatus'                        => 'N',
                    '_charset_'                       => 'UTF-8',
                    'HASH'                            => 'e5KcIY797JNvjrkWjZSfHOa+690=',
                    'rnd'                             => 'mzTLQAaM8W5GuQwu4BfD',
                    'HASHPARAMS'                      => 'clientid:oid:mdStatus:cavv:eci:md:rnd:',
                    'HASHPARAMSVAL'                   => '700655000200202204171C440435508:86D9842A9C594E17B28A2B9037FEB140E8EA480AED5FE19B5CEA446960AA03AA:4122:##700655000200mzTLQAaM8W5GuQwu4BfD',
                ],
            ],
        ];
    }
}
