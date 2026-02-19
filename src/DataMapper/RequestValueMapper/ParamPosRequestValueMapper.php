<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;

class ParamPosRequestValueMapper extends AbstractRequestValueMapper
{
    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => [
            PosInterface::MODEL_NON_SECURE => 'TP_WMD_UCD',
            PosInterface::MODEL_3D_SECURE  => 'TP_WMD_UCD',
            PosInterface::MODEL_3D_PAY     => 'Pos_Odeme',
            PosInterface::MODEL_3D_HOST    => 'TO_Pre_Encrypting_OOS',
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => [
            PosInterface::MODEL_NON_SECURE => 'TP_Islem_Odeme_OnProv_WMD',
            PosInterface::MODEL_3D_SECURE  => 'TP_Islem_Odeme_OnProv_WMD',
        ],
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'TP_Islem_Odeme_OnProv_Kapa',
        PosInterface::TX_TYPE_REFUND         => 'TP_Islem_Iptal_Iade_Kismi2',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'TP_Islem_Iptal_Iade_Kismi2',
        PosInterface::TX_TYPE_CANCEL         => 'TP_Islem_Iptal_Iade_Kismi2',
        PosInterface::TX_TYPE_STATUS         => 'TP_Islem_Sorgulama4',
        PosInterface::TX_TYPE_HISTORY        => 'TP_Islem_Izleme',
    ];

    /**
     * @var non-empty-array<PosInterface::CURRENCY_*, string>
     */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => '1000',
        PosInterface::CURRENCY_USD => '1001',
        PosInterface::CURRENCY_EUR => '1002',
        PosInterface::CURRENCY_GBP => '1003',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE  => '3D',
        PosInterface::MODEL_3D_PAY     => '3D',
        PosInterface::MODEL_NON_SECURE => 'NS',
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ParamPos::class === $gatewayClass
            || Param3DHostPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function mapTxType(string $txType, ?string $paymentModel = null, ?array $order = null): string
    {
        if (isset($order['currency']) && PosInterface::CURRENCY_TRY !== $order['currency']) {
            return 'TP_Islem_Odeme_WD';
        }

        $orderTxType = $order['transaction_type'] ?? null;
        if (PosInterface::TX_TYPE_CANCEL === $txType && PosInterface::TX_TYPE_PAY_PRE_AUTH === $orderTxType) {
            return 'TP_Islem_Iptal_OnProv';
        }

        return parent::mapTxType($txType, $paymentModel);
    }
}
