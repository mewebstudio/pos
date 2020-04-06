<?php

namespace Mews\Pos\Tests;
use Mews\Pos\PosNetCrypt;

use PHPUnit\Framework\TestCase;


class PosNetCryptTest extends TestCase
{
    protected $crypt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crypt = new PosNetCrypt();
    }

    public function testDecrypt(){
        $data = '9ACF38C842B3522415364850EAD1909BD43FD590BE3CBD539AD5FF6C7465973ABD61E8371E03282605ED06C994DF394244B7E7DAD54A046510484FAA724330C4C95A527D7891151E7C195D4136CBD70A87D1BD1F75473CF6B45A3F2FA8231DD71FFB4150E0BF4B133ECAA5ACC82CFD74903E21BC6EECB4B33AF39B8AF0C183A64002CFC125A55685C69A13192F3A9A4FDAC860E90C3FB6D125285E9E687BEFBE05707E131FC7ABE25FE35AB114FAE8A247B8C0F3DBA8AA74396D10564B7A0617EED913ED';
        $key = '10,10,10,10,10,10,10,10';
        $expected_output = '6706598320;67005551;100;00;YKB_TST_090519001330;0;0;https://setmpos.ykb.com/PosnetWebService/YKBTransactionService;posnettest.ykb.com;2225;N;0;Not authenticated;1557398383820;TL';

        $dc = $this->crypt->decrypt($data, $key);
        $this->assertEquals($expected_output, $dc);
    }

}
