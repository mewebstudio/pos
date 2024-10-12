<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Factory\AccountFactory
 * @covers \Mews\Pos\Entity\Account\KuveytPosAccount
 * @covers \Mews\Pos\Entity\Account\AkbankPosAccount
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
}
