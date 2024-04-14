<?php
/**
 * @license MIT
 */

namespace Factory;

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Factory\AccountFactory
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
}
