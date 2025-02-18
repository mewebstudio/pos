<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Serializer\InterPosSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\InterPosSerializer
 */
class InterPosSerializerTest extends TestCase
{
    private InterPosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new InterPosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(InterPos::class);

        $this->assertTrue($supports);
    }

    public function testEncode(): void
    {
        $data = [
            'abc' => '1',
            'sa'  => 'aa',
        ];
        $result = $this->serializer->encode($data);

        $this->assertSame('abc=1&sa=aa', $result);
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $input, array $expected): void
    {
        $result = $this->serializer->decode($input);

        $this->assertSame($expected, $result);
    }

    public static function decodeDataProvider(): array
    {
        return [
            'success_payment' => [
                'input'    => 'OrderId=33554969;;ProcReturnCode=00;;HostRefNum=hostid;;AuthCode=gizlendi;;TxnResult=Success;;ErrorMessage=;;CampanyId=;;CampanyInstallCount=0;;CampanyShiftDateCount=0;;CampanyTxnId=;;CampanyType=;;CampanyInstallment=0;;CampanyDate=0;;CampanyAmnt=0;;TRXDATE=09.08.2024 10:40:34;;TransId=gizlendi;;ErrorCode=;;EarnedBonus=0,00;;UsedBonus=0,00;;AvailableBonus=0,00;;BonusToBonus=0;;CampaignBonus=0,00;;FoldedBonus=0;;SurchargeAmount=0;;Amount=1,00;;CardHolderName=gizlendi;;QrReferenceNumber=;;QrCardToken=;;QrData=;;QrPayIsSucess=False;;QrIssuerPaymentMethod=;;QrFastMessageReferenceNo=;;QrFastParticipantReceiverCode=;;QrFastParticipantReceiverName=;;QrFastParticipantSenderCode=;;QrFastSenderIban=;;QrFastParticipantSenderName=;;QrFastPaymentResultDesc=',
                'expected' => [
                    'OrderId'                       => '33554969',
                    'ProcReturnCode'                => '00',
                    'HostRefNum'                    => 'hostid',
                    'AuthCode'                      => 'gizlendi',
                    'TxnResult'                     => 'Success',
                    'ErrorMessage'                  => '',
                    'CampanyId'                     => '',
                    'CampanyInstallCount'           => '0',
                    'CampanyShiftDateCount'         => '0',
                    'CampanyTxnId'                  => '',
                    'CampanyType'                   => '',
                    'CampanyInstallment'            => '0',
                    'CampanyDate'                   => '0',
                    'CampanyAmnt'                   => '0',
                    'TRXDATE'                       => '09.08.2024 10:40:34',
                    'TransId'                       => 'gizlendi',
                    'ErrorCode'                     => '',
                    'EarnedBonus'                   => '0,00',
                    'UsedBonus'                     => '0,00',
                    'AvailableBonus'                => '0,00',
                    'BonusToBonus'                  => '0',
                    'CampaignBonus'                 => '0,00',
                    'FoldedBonus'                   => '0',
                    'SurchargeAmount'               => '0',
                    'Amount'                        => '1,00',
                    'CardHolderName'                => 'gizlendi',
                    'QrReferenceNumber'             => '',
                    'QrCardToken'                   => '',
                    'QrData'                        => '',
                    'QrPayIsSucess'                 => 'False',
                    'QrIssuerPaymentMethod'         => '',
                    'QrFastMessageReferenceNo'      => '',
                    'QrFastParticipantReceiverCode' => '',
                    'QrFastParticipantReceiverName' => '',
                    'QrFastParticipantSenderCode'   => '',
                    'QrFastSenderIban'              => '',
                    'QrFastParticipantSenderName'   => '',
                    'QrFastPaymentResultDesc'       => '',
                ],
            ],
        ];
    }
}
