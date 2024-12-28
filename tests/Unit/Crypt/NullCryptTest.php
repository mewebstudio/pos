<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\NullCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Crypt\NullCrypt
 */
class NullCryptTest extends TestCase
{
    private NullCrypt $crypt;

    /** @var AbstractPosAccount & MockObject */
    private AbstractPosAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->createMock(AbstractPosAccount::class);

        $this->crypt = new NullCrypt($this->createMock(LoggerInterface::class));
    }

    public function testCreate3DHash(): void
    {
        $this->assertSame('', $this->crypt->create3DHash($this->account, []));
    }

    public function testCheck3DHash(): void
    {
        $this->assertSame(true, $this->crypt->check3DHash($this->account, []));
    }

    public function testCreateHash(): void
    {
        $this->assertSame('', $this->crypt->createHash($this->account, []));
    }
}
