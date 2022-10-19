<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\EstV3Pos;
use PHPUnit\Framework\TestCase;

/**
 * EstV3PosTest
 */
class EstV3PosTest extends TestCase
{
    /** @var EstV3Pos */
    private $pos;


    protected function setUp(): void
    {
        parent::setUp();

        $account = AccountFactory::createEstPosAccount(
            'ziraatv3',
            '700655000212',
            'ISBANKAPI',
            'ISBANK',
            AbstractGateway::MODEL_3D_SECURE,
            'TRPS1234'
        );

        $this->pos = PosFactory::createPosGateway($account);

        $this->pos->setTestMode(true);
    }

    /**
     * @return void
     */
    public function testCheck3DHash()
    {
        $data = $this->get3DMakePaymentFailResponseData();
        $this->assertTrue($this->pos->check3DHash($data));

        $data['mdStatus'] = '';
        $this->assertFalse($this->pos->check3DHash($data));
    }

    /**
     * @return string[]
     */
    private function get3DMakePaymentFailResponseData(): array
    {
        return [
            'TRANID' => '',
            'PAResSyntaxOK' => 'true',
            'lang' => 'tr',
            'merchantID' => '700655000200',
            'maskedCreditCard' => '4355 08** **** 4358',
            'amount' => '1.01',
            'sID' => '1',
            'ACQBIN' => '406456',
            'Ecom_Payment_Card_ExpDate_Year' => '30',
            'MaskedPan' => '435508***4358',
            'clientIp' => '89.244.149.137',
            'iReqDetail' => '',
            'okUrl' => 'http://localhost/akbank/3d/response.php',
            'md' => '435508:86D9842A9C594E17B28A2B9037FEB140E8EA480AED5FE19B5CEA446960AA03AA:4122:##700655000200',
            'vendorCode' => '',
            'Ecom_Payment_Card_ExpDate_Month' => '12',
            'storetype' => '3d',
            'iReqCode' => '',
            'mdErrorMsg' => 'Not authenticated',
            'PAResVerified' => 'false',
            'cavv' => '',
            'digest' => 'digest',
            'callbackCall' => 'true',
            'failUrl' => 'http://localhost/akbank/3d/response.php',
            'cavvAlgorithm' => '',
            'xid' => 'FKqfXqwd0VA5RILtjmwaW17t/jk=',
            'encoding' => 'ISO-8859-9',
            'currency' => '949',
            'oid' => '202204171C44',
            'mdStatus' => '0',
            'dsId' => '1',
            'eci' => '',
            'version' => '2.0',
            'clientid' => '700655000200',
            'txstatus' => 'N',
            '_charset_' => 'UTF-8',
            'HASH' => '9xdS+xNZEnPj0MIPu/K091fJw3Sid4Zpscq6UqxCbzikJoGDlBx4WrRIq8HTF0s8SOrLCjF9E3/kzxBwci9vIQ==',
            'rnd' => 'mzTLQAaM8W5GuQwu4BfD',
        ];
    }
}
