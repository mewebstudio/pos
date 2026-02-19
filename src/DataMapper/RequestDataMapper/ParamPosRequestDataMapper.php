<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\DataMapper\RequestValueFormatter\ParamPosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\RequestValueMapper\ParamPosRequestValueMapper;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\ParamPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for ParamPos Gateway requests
 */
class ParamPosRequestDataMapper extends AbstractRequestDataMapper
{
    /**
     * @var ParamPosRequestValueMapper
     */
    protected RequestValueMapperInterface $valueMapper;

    /**
     * @var ParamPosRequestValueFormatter
     */
    protected RequestValueFormatterInterface $valueFormatter;

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ParamPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $requestData = $this->getRequestAccountData($posAccount) + [
                '@xmlns'     => 'https://turkpos.com.tr/',
                'UCD_MD'     => (string) $responseData['md'],
                'Islem_GUID' => (string) $responseData['islemGUID'],
                'Siparis_ID' => (string) $responseData['orderId'],
            ];

        return $this->wrapSoapEnvelope(['TP_WMD_Pay' => $requestData]);
    }

    /**
     * @param ParamPosAccount                      $posAccount
     * @param array<string, int|string|float|null> $order
     * @param CreditCardInterface                  $creditCard
     * @param PosInterface::TX_TYPE_PAY_*          $txType
     * @param PosInterface::MODEL_3D_*             $paymentModel
     *
     * @return array<string, mixed>
     */
    public function create3DEnrollmentCheckRequestData(AbstractPosAccount $posAccount, array $order, ?CreditCardInterface $creditCard, string $txType, string $paymentModel): array
    {
        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            throw new \InvalidArgumentException();
        }

        if (!$creditCard instanceof \Mews\Pos\Entity\Card\CreditCardInterface) {
            throw new \InvalidArgumentException('Bu işlem için kredi kartı bilgileri gereklidir.');
        }

        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                '@xmlns'             => 'https://turkpos.com.tr/',
                'Islem_Guvenlik_Tip' => $this->valueMapper->mapSecureType($paymentModel),
                'Islem_ID'           => $this->crypt->generateRandomString(),
                'IPAdr'              => (string) $order['ip'],
                'Siparis_ID'         => (string) $order['id'],
                'Islem_Tutar'        => $this->valueFormatter->formatAmount($order['amount'], $txType),
                'Toplam_Tutar'       => $this->valueFormatter->formatAmount($order['amount'], $txType),
                'Basarili_URL'       => (string) $order['success_url'],
                'Hata_URL'           => (string) $order['fail_url'],
                'Taksit'             => $this->valueFormatter->formatInstallment((int) $order['installment']),
                'KK_Sahibi'          => $creditCard->getHolderName(),
                'KK_No'              => $creditCard->getNumber(),
                'KK_SK_Ay'           => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'KK_SK_Ay'),
                'KK_SK_Yil'          => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'KK_SK_Yil'),
                'KK_CVC'             => $creditCard->getCvv(),
                'KK_Sahibi_GSM'      => '', //optional olmasina ragmen hic gonderilmeyince hata aliniyor.
            ];

        if (PosInterface::CURRENCY_TRY !== $order['currency']) {
            $requestData['Doviz_Kodu'] = $this->valueMapper->mapCurrency($order['currency']);
        }

        $soapAction = $this->valueMapper->mapTxType($txType, $paymentModel, $order);

        $requestData = [$soapAction => $requestData];

        $requestData[$soapAction]['Islem_Hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $this->wrapSoapEnvelope($requestData);
    }

    /**
     * {@inheritDoc}
     * @return array<string, string|array<string, string>>
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                '@xmlns'             => 'https://turkpos.com.tr/',
                'Islem_Guvenlik_Tip' => $this->valueMapper->mapSecureType(PosInterface::MODEL_NON_SECURE),
                'Islem_ID'           => $this->crypt->generateRandomString(),
                'IPAdr'              => (string) $order['ip'],
                'Siparis_ID'         => (string) $order['id'],
                'Islem_Tutar'        => $this->valueFormatter->formatAmount($order['amount'], $txType),
                'Toplam_Tutar'       => $this->valueFormatter->formatAmount($order['amount'], $txType),
                'Taksit'             => $this->valueFormatter->formatInstallment((int) $order['installment']),
                'KK_Sahibi'          => $creditCard->getHolderName(),
                'KK_No'              => $creditCard->getNumber(),
                'KK_SK_Ay'           => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'KK_SK_Ay'),
                'KK_SK_Yil'          => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'KK_SK_Yil'),
                'KK_CVC'             => $creditCard->getCvv(),
                'KK_Sahibi_GSM'      => '', //optional olmasina ragmen hic gonderilmeyince hata aliniyor.
            ];

        if (PosInterface::CURRENCY_TRY !== $order['currency']) {
            $requestData['Doviz_Kodu']   = $this->valueMapper->mapCurrency($order['currency']);
            $requestData['Basarili_URL'] = (string) $order['success_url'];
            $requestData['Hata_URL']     = (string) $order['fail_url'];
        }

        $soapAction = $this->valueMapper->mapTxType($txType, PosInterface::MODEL_NON_SECURE, $order);

        if (PosInterface::TX_TYPE_PAY_PRE_AUTH === $txType) {
            $soapAction = $this->valueMapper->mapTxType($txType, PosInterface::MODEL_NON_SECURE);
        }

        $requestData = [$soapAction => $requestData];

        $requestData[$soapAction]['Islem_Hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $this->wrapSoapEnvelope($requestData);
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                '@xmlns'     => 'https://turkpos.com.tr/',
                'Prov_ID'    => '',
                'Prov_Tutar' => $this->valueFormatter->formatAmount($order['amount'], PosInterface::TX_TYPE_PAY_POST_AUTH),
                'Siparis_ID' => (string) $order['id'],
            ];

        return $this->wrapSoapEnvelope([
            $this->valueMapper->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH) => $requestData,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                '@xmlns'     => 'https://turkpos.com.tr/',
                'Siparis_ID' => $order['id'],
            ];

        return $this->wrapSoapEnvelope([
            $this->valueMapper->mapTxType(PosInterface::TX_TYPE_STATUS) => $requestData,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        if (PosInterface::TX_TYPE_PAY_PRE_AUTH === $order['transaction_type']) {
            $requestData = $this->getRequestAccountData($posAccount) + [
                    '@xmlns'     => 'https://turkpos.com.tr/',
                    'Prov_ID'    => null,
                    'Siparis_ID' => $order['id'],
                ];
        } else {
            $requestData = $this->getRequestAccountData($posAccount) + [
                    '@xmlns'     => 'https://turkpos.com.tr/',
                    'Durum'      => 'IPTAL',
                    'Siparis_ID' => $order['id'],
                    'Tutar'      => $this->valueFormatter->formatAmount($order['amount'], PosInterface::TX_TYPE_CANCEL),
                ];
        }

        return $this->wrapSoapEnvelope([
            $this->valueMapper->mapTxType(PosInterface::TX_TYPE_CANCEL, null, $order) => $requestData,
        ]);
    }


    /**
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                '@xmlns'     => 'https://turkpos.com.tr/',
                'Durum'      => 'IADE',
                'Siparis_ID' => $order['id'],
                'Tutar'      => $this->valueFormatter->formatAmount($order['amount'], $refundTxType),
            ];

        return $this->wrapSoapEnvelope([
            $this->valueMapper->mapTxType($refundTxType) => $requestData,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }


    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array
    {
        $order = $this->prepareHistoryOrder($data);

        $requestData = $this->getRequestAccountData($posAccount) + [
                '@xmlns'    => 'https://turkpos.com.tr/',
                'Tarih_Bas' => $this->valueFormatter->formatDateTime($order['start_date'], 'Tarih_Bas'),
                'Tarih_Bit' => $this->valueFormatter->formatDateTime($order['end_date'], 'Tarih_Bit'),
            ];

        if (isset($data['order_status'])) {
            $requestData['Islem_Durum'] = $data['order_status'];
        }

        if (isset($data['transaction_type'])) {
            if (PosInterface::TX_TYPE_PAY_AUTH === $data['transaction_type']) {
                $requestData['Islem_Tip'] = 'Satış';
            } elseif (PosInterface::TX_TYPE_CANCEL === $data['transaction_type']) {
                $requestData['Islem_Tip'] = 'İptal';
            } elseif (PosInterface::TX_TYPE_REFUND === $data['transaction_type']) {
                $requestData['Islem_Tip'] = 'İade';
            }
        }

        return $this->wrapSoapEnvelope([
            $this->valueMapper->mapTxType(PosInterface::TX_TYPE_HISTORY) => $requestData,
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, mixed> $extraData
     */
    public function create3DFormData(
        ?AbstractPosAccount  $posAccount,
        ?array               $order,
        string               $paymentModel,
        string               $txType,
        ?string              $gatewayURL = null,
        ?CreditCardInterface $creditCard = null,
        array                $extraData = []
    ) {
        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            throw new \InvalidArgumentException();
        }

        if (PosInterface::MODEL_3D_PAY === $paymentModel) {
            $tempData = $extraData['Pos_OdemeResponse']['Pos_OdemeResult'];

            return [
                'gateway' => $this->extractBaseUrlFromUrl($tempData['UCD_URL']),
                'method'  => 'GET',
                'inputs'  => $this->extractQueryParamsAsArrayFromUrl($tempData['UCD_URL']),
            ];
        }

        $currency = $order['currency'] ?? PosInterface::CURRENCY_TRY;

        if (PosInterface::CURRENCY_TRY !== $currency) {
            $tempData = $extraData['TP_Islem_Odeme_WDResponse']['TP_Islem_Odeme_WDResult'];

            return [
                'gateway' => $this->extractBaseUrlFromUrl($tempData['UCD_URL']),
                'method'  => 'GET',
                'inputs'  => $this->extractQueryParamsAsArrayFromUrl($tempData['UCD_URL']),
            ];
        }

        if (PosInterface::TX_TYPE_PAY_PRE_AUTH === $txType) {
            $tempData = $extraData['TP_Islem_Odeme_OnProv_WMDResponse']['TP_Islem_Odeme_OnProv_WMDResult'];
        } else {
            $tempData = $extraData['TP_WMD_UCDResponse']['TP_WMD_UCDResult'];
        }

        if ('' === $tempData['UCD_HTML']) {
            throw new \RuntimeException('3D form verisi oluşturulamadı! UCD_HTML değeri boş.');
        }

        return $tempData['UCD_HTML'];
    }

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        /** @var string $soapAction */
        $soapAction               = \array_key_first($requestData);
        $requestData[$soapAction] += $this->getRequestAccountData($posAccount);

        return $this->wrapSoapEnvelope($requestData);
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        return \array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'amount'      => $order['amount'],
            'ip'          => $order['ip'],
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return [
            'id'       => $order['id'],
            'amount'   => $order['amount'],
            'currency' => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        return [
            'id'       => $order['id'],
            'currency' => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'amount'   => $order['amount'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order): array
    {
        return \array_merge($order, [
            'transaction_type' => $order['transaction_type'],
            'id'               => $order['id'],
            'amount'           => $order['amount'] ?? null,
        ]);
    }

    /**
     * @param AbstractPosAccount $posAccount
     *
     * @return array{G: array{CLIENT_CODE: string, CLIENT_USERNAME: string, CLIENT_PASSWORD: string}}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'G'    => [
                'CLIENT_CODE'     => $posAccount->getClientId(),
                'CLIENT_USERNAME' => $posAccount->getUsername(),
                'CLIENT_PASSWORD' => $posAccount->getPassword(),
            ],
            'GUID' => $posAccount->getStoreKey(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{"soap:Body": array<string, mixed>}
     */
    private function wrapSoapEnvelope(array $data): array
    {
        return [
            'soap:Body' => $data,
        ];
    }

    /**
     * @param string $url
     *
     * @return array<string, string>
     */
    private function extractQueryParamsAsArrayFromUrl(string $url): array
    {
        $parsedUrl = \parse_url($url);
        if (!isset($parsedUrl['query'])) {
            throw new \InvalidArgumentException(\sprintf('Hatalı URL "%s". Query parametreleri bulunmamakta!', $url));
        }

        \parse_str($parsedUrl['query'], $queryParams);

        /** @var array<string, string> $queryParams */

        return $queryParams;
    }

    /**
     * @param string $url
     *
     * @return string
     */
    private function extractBaseUrlFromUrl(string $url): string
    {
        $parsedUrl = \parse_url($url);
        if (!isset($parsedUrl['scheme'], $parsedUrl['host'])) {
            throw new \InvalidArgumentException(\sprintf('Hatalı URL "%s"!', $url));
        }

        // Get the base URL
        $baseUrl = $parsedUrl['scheme'].'://'.$parsedUrl['host'];
        if (isset($parsedUrl['port'])) {
            $baseUrl .= ':'.$parsedUrl['port'];
        }

        if (isset($parsedUrl['path'])) {
            $baseUrl .= $parsedUrl['path'];
        }

        return $baseUrl;
    }
}
