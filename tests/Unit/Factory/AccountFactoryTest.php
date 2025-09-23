<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Factory\AccountFactory
 * @covers \Mews\Pos\Entity\Account\KuveytPosAccount
 * @covers \Mews\Pos\Entity\Account\AkbankPosAccount
 * @covers \Mews\Pos\Entity\Account\ParamPosAccount
 * @covers \Mews\Pos\Entity\Account\PayForAccount
 */
class AccountFactoryTest extends TestCase
{
    public function testCreateKuveytPosAccount(): void
    {
        $account = AccountFactory::createKuveytPosAccount(
            'vakif-katilim',
            '1',
            'APIUSER',
            '11111',
            'kdsnsksl',
            PosInterface::MODEL_3D_SECURE,
            PosInterface::LANG_TR,
            'SUB1',
        );

        $this->assertSame('1', $account->getClientId());
        $this->assertSame('APIUSER', $account->getUsername());
        $this->assertSame('11111', $account->getCustomerId());
        $this->assertSame('kdsnsksl', $account->getStoreKey());
        $this->assertSame('SUB1', $account->getSubMerchantId());
    }

    public function testCreateAkbankPosAccount(): void
    {
        $account = AccountFactory::createAkbankPosAccount(
            'akbank-pos',
            '1',
            'APIUSER',
            'kdsnsksl',
            PosInterface::LANG_EN,
            'SUB1',
        );

        $this->assertSame('1', $account->getClientId());
        $this->assertSame('APIUSER', $account->getTerminalId());
        $this->assertSame('kdsnsksl', $account->getStoreKey());
        $this->assertSame('SUB1', $account->getSubMerchantId());
        $this->assertSame(PosInterface::LANG_EN, $account->getLang());
    }

    public function testCreateParamPosAccount(): void
    {
        $account = AccountFactory::createParamPosAccount(
            'param-pos',
            '12345',
            'APIUSER',
            'kdsnsksl',
            'guid123',
        );

        $this->assertSame('12345', $account->getClientId());
        $this->assertSame('APIUSER', $account->getUsername());
        $this->assertSame('kdsnsksl', $account->getPassword());
        $this->assertSame('guid123', $account->getStoreKey());
        $this->assertSame(PosInterface::LANG_TR, $account->getLang());
    }

    public function testCreatePayForPosAccount(): void
    {
        $account = AccountFactory::createPayForAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            PosInterface::MODEL_3D_SECURE,
            '12345678',
            PosInterface::LANG_EN,
            \Mews\Pos\Entity\Account\PayForAccount::MBR_ID_ZIRAAT_KATILIM
        );

        $this->assertSame('085300000009704', $account->getClientId());
        $this->assertSame('QNB_API_KULLANICI_3DPAY', $account->getUsername());
        $this->assertSame('UcBN0', $account->getPassword());
        $this->assertSame('12345678', $account->getStoreKey());
        $this->assertSame(PosInterface::LANG_EN, $account->getLang());
        $this->assertSame(PayForAccount::MBR_ID_ZIRAAT_KATILIM, $account->getMbrId());
    }
}
