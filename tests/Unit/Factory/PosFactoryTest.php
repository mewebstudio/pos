<?php

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;
use Mews\Pos\Factory\PosFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Factory\PosFactory
 */
class PosFactoryTest extends TestCase
{
    /**
     * @dataProvider createPosGatewayDataProvider
     */
    public function testCreatePosGateway(array $config, string $configKey, bool $cardTypeMapping, string $expectedGatewayClass): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $account->expects(self::atLeastOnce())
            ->method('getBank')
            ->willReturn($configKey);

        $eventDispatcher = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $httpClient      = $this->createMock(\Mews\Pos\Client\HttpClient::class);
        $logger          = $this->createMock(\Psr\Log\LoggerInterface::class);

        $gateway = PosFactory::createPosGateway(
            $account,
            $config,
            $eventDispatcher,
            $httpClient,
            $logger
        );
        $this->assertInstanceOf($expectedGatewayClass, $gateway);

        $this->assertSame($account, $gateway->getAccount());
        $this->assertNotEmpty($gateway->getCurrencies());
        if ($cardTypeMapping) {
            $this->assertNotEmpty($gateway->getCardTypeMapping());
        } else {
            $this->assertEmpty($gateway->getCardTypeMapping());
        }
    }


    public function testCreatePosGatewayWithOnlyRequiredParameters(): void
    {
        $gatewayClass = \Mews\Pos\Gateways\AkbankPos::class;
        $config       = [
            'banks' => [
                'akbank' => [
                    'name'              => 'Akbank',
                    'class'             => $gatewayClass,
                    'gateway_endpoints' => [
                        'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
                        'gateway_3d'      => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                        'gateway_3d_host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
                    ],
                ],
            ],
        ];
        $account      = $this->createMock(AbstractPosAccount::class);
        $account->expects(self::atLeastOnce())
            ->method('getBank')
            ->willReturn('akbank');

        $eventDispatcher = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);

        $gateway = PosFactory::createPosGateway(
            $account,
            $config,
            $eventDispatcher,
        );
        $this->assertInstanceOf($gatewayClass, $gateway);
    }

    /**
     * @dataProvider createPosGatewayDataExceptionProvider
     */
    public function testCreatePosGatewayFail(array $config, string $configKey, string $expectedExceptionClass): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $account->expects(self::atLeastOnce())
            ->method('getBank')
            ->willReturn($configKey);

        $eventDispatcher = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);

        $this->expectException($expectedExceptionClass);
        PosFactory::createPosGateway(
            $account,
            $config,
            $eventDispatcher,
        );
    }

    public static function createPosGatewayDataExceptionProvider(): \Generator
    {
        yield 'missing_gateway_class_in_config' => [
            'config'                   => [
                'banks' => [
                    'akbank' => [
                        'name'              => 'Akbank',
                        'gateway_endpoints' => [
                            'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
                            'gateway_3d'      => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                            'gateway_3d_host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
                        ],
                    ],
                ],
            ],
            'config_key'               => 'akbank',
            'expected_exception_class' => BankClassNullException::class,
        ];

        yield 'invalid_gateway_class' => [
            'config'                   => [
                'banks' => [
                    'akbank' => [
                        'name'              => 'Akbank',
                        'class'             => \stdClass::class,
                        'gateway_endpoints' => [
                            'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
                            'gateway_3d'      => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                            'gateway_3d_host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
                        ],
                    ],
                ],
            ],
            'config_key'               => 'akbank',
            'expected_exception_class' => \InvalidArgumentException::class,
        ];

        yield 'non_existing_config_key' => [
            'config'                   => [
                'banks' => [
                    'estpos' => [
                        'name'              => 'Akbank',
                        'class'             => \stdClass::class,
                        'gateway_endpoints' => [
                            'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
                            'gateway_3d'      => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                            'gateway_3d_host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
                        ],
                    ],
                ],
            ],
            'config_key'               => 'akbank',
            'expected_exception_class' => BankNotFoundException::class,
        ];
    }

    public static function createPosGatewayDataProvider(): \Generator
    {
        $gatewayClasses = [
            \Mews\Pos\Gateways\AkbankPos::class       => false,
            \Mews\Pos\Gateways\EstPos::class          => false,
            \Mews\Pos\Gateways\EstV3Pos::class        => false,
            \Mews\Pos\Gateways\GarantiPos::class      => false,
            \Mews\Pos\Gateways\InterPos::class        => true,
            \Mews\Pos\Gateways\KuveytPos::class       => true,
            \Mews\Pos\Gateways\PayFlexCPV4Pos::class  => true,
            \Mews\Pos\Gateways\PayFlexV4Pos::class    => true,
            \Mews\Pos\Gateways\PayForPos::class       => false,
            \Mews\Pos\Gateways\PosNet::class          => false,
            \Mews\Pos\Gateways\PosNetV1Pos::class     => false,
            \Mews\Pos\Gateways\ToslaPos::class        => false,
            \Mews\Pos\Gateways\VakifKatilimPos::class => false,
        ];

        foreach ($gatewayClasses as $gatewayClass => $cardTypeMapping) {
            $configKey = 'abcdse';
            $config    = [
                'banks' => [
                    $configKey => [
                        'name'              => 'Akbank',
                        'class'             => $gatewayClass,
                        'gateway_endpoints' => [
                            'payment_api'     => 'https://apipre.akbank.com/api/v1/payment/virtualpos',
                            'gateway_3d'      => 'https://virtualpospaymentgatewaypre.akbank.com/securepay',
                            'gateway_3d_host' => 'https://virtualpospaymentgatewaypre.akbank.com/payhosting',
                        ],
                    ],
                ],
            ];
            yield [
                $config,
                $configKey,
                $cardTypeMapping,
                $gatewayClass,
            ];
        }
    }
}
