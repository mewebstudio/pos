<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\GarantiPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Crypt\GarantiPosCrypt
 * @covers \Mews\Pos\Crypt\AbstractCrypt
 */
class GarantiPosCryptTest extends TestCase
{
    private GarantiPosAccount $account;

    private GarantiPosCrypt $crypt;

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

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new GarantiPosCrypt($logger);
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(bool $expected, array $responseData): void
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->account, $responseData));

        $responseData['mdstatus'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->account, $responseData));
    }

    public function testCheck3DHashException(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(\LogicException::class);
        $this->crypt->check3DHash($account, []);
    }

    /**
     * @return void
     */
    public function testCreate3DHash(): void
    {
        $requestData = [
            'terminalid'          => '30691298',
            'orderid'             => 'order222',
            'txnamount'           => 10025,
            'txninstallmentcount' => '',
            'txntype'             => 'sales',
            'txncurrencycode'     => '949',
            'successurl'          => 'https://domain.com/success',
            'errorurl'            => 'https://domain.com/fail_url',
        ];

        $expected = '372D6CB20B2B699D0A6667DFF46E3AA8CF3F9D8C2BB69A7C411895151FFCFAAB5277CCFE3B3A06035FEEFBFBFD40C79DBE51DBF867D0A24B37335A28F0CEFDE2';
        $actual   = $this->crypt->create3DHash($this->account, $requestData);
        $this->assertSame($expected, $actual);
    }


    /**
     * @dataProvider hashCreateDataProvider
     */
    public function testCreateHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->createHash($this->account, $requestData);
        $this->assertSame($expected, $actual);
    }

    public static function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => [
                    'xid'                   => 'f3ec4783-f48c-475c-a59c-ab25f3170ec5',
                    'mdstatus'              => '1',
                    'mderrormessage'        => 'Y-status/Challenge authentication via ACS: https://gbemv3dsecure.garanti.com.tr/web/creq',
                    'txnstatus'             => '',
                    'eci'                   => '02',
                    'cavv'                  => 'xgRlQDz4AAAAAAAAAAAAAAAAAAA=',
                    'paressyntaxok'         => '',
                    'paresverified'         => '',
                    'version'               => '',
                    'ireqcode'              => '',
                    'ireqdetail'            => '',
                    'vendorcode'            => '',
                    'cavvalgorithm'         => '',
                    'md'                    => 'aW5kZXg6MDJ6LjAI5iAcKf/ilXjYIOnTh4t+deHrtwO8ze7tPTL1YCDcBe8KEpuq6HDLYbqQSluL7p3kGcpFzX9s9XcegNhHMsDszxqGd33+p+p5sULGrDF3J2GGfiJDwan4ku7+eiTyS8x2xS9pUy7PTgMGc6jw94aLfXLHskhvY7FYWrymzQ==',
                    'terminalid'            => '30691298',
                    'oid'                   => '2023100354BB',
                    'authcode'              => '',
                    'response'              => '',
                    'errmsg'                => '',
                    'hostmsg'               => '',
                    'procreturncode'        => '',
                    'transid'               => '2023100354BB',
                    'hostrefnum'            => '',
                    'rnd'                   => 'kW094tPzNEhqORzzCsLB',
                    'hash'                  => '416B6253425E73184F118CC02E3BAA393622059BF6B0865D83F501E55A61339B9EC659CBCF7297EDECC1B17BF6281D90CC0AD8EDF3E1EFE94432ACCEAF79B26E',
                    'hashparams'            => 'clientid:oid:authcode:procreturncode:response:mdstatus:cavv:eci:md:rnd:',
                    'hashparamsval'         => '306912982023100354BB1xgRlQDz4AAAAAAAAAAAAAAAAAAA=02aW5kZXg6MDJ6LjAI5iAcKf/ilXjYIOnTh4t+deHrtwO8ze7tPTL1YCDcBe8KEpuq6HDLYbqQSluL7p3kGcpFzX9s9XcegNhHMsDszxqGd33+p+p5sULGrDF3J2GGfiJDwan4ku7+eiTyS8x2xS9pUy7PTgMGc6jw94aLfXLHskhvY7FYWrymzQ==kW094tPzNEhqORzzCsLB',
                    'clientid'              => '30691298',
                    'MaskedPan'             => '54066975****1173',
                    'apiversion'            => '512',
                    'orderid'               => '2023100354BB',
                    'txninstallmentcount'   => '',
                    'terminaluserid'        => 'PROVAUT',
                    'secure3dhash'          => 'B0EE6F6405ABB6EF014D802880EF3DC72CEA1EFD16E7E346A4CD6F6EE6ED2148FA8DCFD703EAEA9C154C7C200CF42D00A874832D6D3F22F9447EDF241D540286',
                    'secure3dsecuritylevel' => '3D',
                    'txncurrencycode'       => '949',
                    'customeremailaddress'  => 'mail@customer.com',
                    'errorurl'              => 'http://localhost/garanti/3d/response.php',
                    'terminalmerchantid'    => '7000679',
                    'mode'                  => 'TEST',
                    'terminalprovuserid'    => 'PROVAUT',
                    'txnamount'             => '101',
                    'successurl'            => 'http://localhost/garanti/3d/response.php',
                    'txntype'               => 'sales',
                    'customeripaddress'     => '172.26.0.1',
                ],
            ],
        ];
    }

    public static function hashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'Terminal'    => [
                        'ID' => '30691298',
                    ],
                    'Order'       => [
                        'OrderID' => 'order222',
                    ],
                    'Transaction' => [
                        'Type'         => 'sales',
                        'Amount'       => 10025,
                        'CurrencyCode' => '949',
                    ],
                ],
                'expected'    => '0CFE09F107274C6A07292DA061A4EECAB0F5F0CF87F831F2D3626A3346A941126C52D1D95A3B77ADF5AC348B3D25C76BA5D8D98A29557D087D3367BFFACCD25C',
            ],
            [
                'requestData' => [
                    'Terminal'    => [
                        'ID' => '30691298',
                    ],
                    'Order'       => [
                        'OrderID' => 'order222',
                    ],
                    'Transaction' => [
                        'Type'         => 'preauth',
                        'Amount'       => 10025,
                        'CurrencyCode' => '949',
                    ],
                ],
                'expected'    => '0CFE09F107274C6A07292DA061A4EECAB0F5F0CF87F831F2D3626A3346A941126C52D1D95A3B77ADF5AC348B3D25C76BA5D8D98A29557D087D3367BFFACCD25C',
            ],
            [
                'requestData' => [
                    'Terminal'    => [
                        'ID' => '30691298',
                    ],
                    'Order'       => [
                        'OrderID' => '4499996',
                    ],
                    'Transaction' => [
                        'Type'         => 'void',
                        // for cancel request amount is always 100
                        'Amount'       => 100,
                        'CurrencyCode' => '949',
                    ],
                ],
                'expected'    => 'C0C88761726D1844AF49FBE756DBE4586F7339169994CF700AE50AA8EAD0C8E81F5828C3392CE39CFC3F8C976FC3E24B576F4DB55DAF2E7D3A6F6B6E5E89B189',
            ],
            [
                'requestData' => [
                    'Terminal'    => [
                        'ID' => '30691298',
                    ],
                    'Order'       => [
                        'OrderID' => '4499996',
                    ],
                    'Transaction' => [
                        'Type'         => 'refund',
                        'Amount'       => 202,
                        'CurrencyCode' => '949',
                    ],
                ],
                'expected'    => '0F97D922001221B9C90AA692CF5D4082FF6D3EB38BE863A47F9C08E63CD87312270D6F298E5FBBC320654861DA1C6EE826E0C83E904916351A9D3032FA426BAA',
            ],
            'bininq' => [
                'requestData' => [
                    'Version'     => 'v0.00',
                    'Terminal'    => [
                        'ID' => '30691298',
                    ],
                    'Customer'    => [
                        'IPAddress'    => '1.1.111.111',
                        'EmailAddress' => 'Cem@cem.com',
                    ],
                    'Order'       => [
                        'OrderID'     => 'SISTD5A61F1682E745B28871872383ABBEB1',
                        'GroupID'     => '',
                        'Description' => '',
                    ],
                    'Transaction' => [
                        'Type'   => 'bininq',
                        'Amount' => '1',
                        'BINInq' => [
                            'Group'    => 'A',
                            'CardType' => 'A',
                        ],
                    ],
                ],
                'expected'    => 'B129BF998FF8C97C42D3FC923AA4271656B112549665FA130ACF631EA3EB73D917AEB3D646481564CACE38D1687F7744F82E5A1DBB6966F1512E0AF29B4C067B',
            ],
        ];
    }
}
