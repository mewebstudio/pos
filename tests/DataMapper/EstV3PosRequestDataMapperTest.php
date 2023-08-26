<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\DataMapper;

use Mews\Pos\DataMapper\EstV3PosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EstV3PosRequestDataMapperTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $threeDAccount;

    /** @var EstPos */
    private $pos;

    /** @var AbstractCreditCard */
    private $card;

    /** @var EstV3PosRequestDataMapper */
    private $requestDataMapper;

    private $order;

    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos_test.php';

        $this->threeDAccount = AccountFactory::createEstPosAccount(
            'akbankv3',
            '190100000',
            'ZIRAATAPI',
            'ZIRAAT19',
            AbstractGateway::MODEL_3D_SECURE,
            '123456'
        );

        $this->order = [
            'id'          => 'order222',
            'ip'          => '127.0.0.1',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => 'TRY',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'rand'        => microtime(),
        ];

        $this->pos = PosFactory::createPosGateway($this->threeDAccount, $this->config);
        $this->pos->setTestMode(true);

        $crypt = PosFactory::getGatewayCrypt(EstV3Pos::class, new NullLogger());
        $this->requestDataMapper = new EstV3PosRequestDataMapper($crypt);
        $this->card              = CreditCardFactory::create($this->pos, '5555444433332222', '22', '01', '123', 'ahmet', AbstractCreditCard::CARD_TYPE_VISA);
    }

    /**
     * @return void
     */
    public function testGet3DFormData()
    {
        $account = $this->threeDAccount;
        $txType  = AbstractGateway::TX_PAY;
        $this->pos->prepare($this->order, $txType);
        $card       = $this->card;
        $gatewayURL = $this->config['banks'][$this->threeDAccount->getBank()]['gateway_endpoints']['gateway_3d'];

        $inputs = [
            'clientid'      => $account->getClientId(),
            'storetype'     => AbstractGateway::MODEL_3D_SECURE,
            'firmaadi'      => $this->order['name'],
            'Email'         => $this->order['email'],
            'amount'        => $this->order['amount'],
            'oid'           => $this->order['id'],
            'okUrl'         => $this->order['success_url'],
            'failUrl'       => $this->order['fail_url'],
            'rnd'           => $this->order['rand'],
            'hashAlgorithm' => 'ver3',
            'lang'          => 'tr',
            'currency'      => 949,
            'islemtipi'     => 'Auth',
            'taksit'        => '',
        ];

        $hash           = $this->requestDataMapper->getCrypt()->create3DHash($account, $inputs, $txType);
        $inputs['hash'] = $hash;
        $form           = [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
        //test without card
        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->pos->getAccount(),
            $this->pos->getOrder(),
            AbstractGateway::MODEL_3D_SECURE,
            $txType,
            $gatewayURL
        ));

        //test with card
        $form['inputs']['cardType']                        = '1';
        $form['inputs']['pan']                             = $card->getNumber();
        $form['inputs']['Ecom_Payment_Card_ExpDate_Month'] = '01';
        $form['inputs']['Ecom_Payment_Card_ExpDate_Year']  = '22';
        $form['inputs']['cv2']                             = $card->getCvv();

        unset($form['inputs']['hash']);
        $form['inputs']['hash'] = $this->requestDataMapper->getCrypt()->create3DHash($account, $form['inputs'], $txType);

        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->pos->getAccount(),
            $this->pos->getOrder(),
            AbstractGateway::MODEL_3D_SECURE,
            $txType,
            $gatewayURL,
            $card
        ));
    }

    /**
     * @return void
     */
    public function testGet3DHostFormData()
    {
        $account = AccountFactory::createEstPosAccount(
            'akbankv3',
            'XXXXXXX',
            'XXXXXXX',
            'XXXXXXX',
            AbstractGateway::MODEL_3D_HOST,
            'VnM5WZ3sGrPusmWP'
        );

        /** @var EstV3Pos $pos */
        $pos = PosFactory::createPosGateway($account, $this->config);
        $pos->setTestMode(true);
        $pos->prepare($this->order, AbstractGateway::TX_PAY);

        $gatewayURL = $this->pos->get3DHostGatewayURL();

        $inputs     = [
            'clientid'  => $account->getClientId(),
            'storetype' => AbstractGateway::MODEL_3D_HOST,
            'firmaadi'  => $this->order['name'],
            'Email'     => $this->order['email'],
            'amount'    => $this->order['amount'],
            'oid'       => $this->order['id'],
            'okUrl'     => $this->order['success_url'],
            'failUrl'   => $this->order['fail_url'],
            'rnd'       => $this->order['rand'],
            'hashAlgorithm' => 'ver3',
            'lang'      => 'tr',
            'currency'  => '949',
            'islemtipi' => 'Auth',
            'taksit'    => '',
        ];
        $form       = [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
        $form['inputs']['hash']       = $this->requestDataMapper->getCrypt()->create3DHash($account, $inputs, AbstractGateway::TX_PAY);

        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $pos->getAccount(),
            $pos->getOrder(),
            AbstractGateway::MODEL_3D_HOST,
            AbstractGateway::TX_PAY,
            $gatewayURL
        ));
    }
}
