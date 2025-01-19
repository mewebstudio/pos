<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\ParamPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;

/**
 * Creates request data for ParamPoss Gateway requests
 */
class ParamPosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const CREDIT_CARD_EXP_MONTH_FORMAT = 'm';

    /** @var string */
    public const CREDIT_CARD_EXP_YEAR_FORMAT = 'Y';

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
     * {@inheritdoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE  => '3D',
        PosInterface::MODEL_3D_PAY     => '3D',
        PosInterface::MODEL_NON_SECURE => 'NS',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => '1000',
        PosInterface::CURRENCY_USD => '1001',
        PosInterface::CURRENCY_EUR => '1002',
        PosInterface::CURRENCY_GBP => '1003',
    ];

    /**
     * @param PosInterface::TX_TYPE_*          $txType
     * @param PosInterface::MODEL_*|null       $paymentModel
     * @param PosInterface::TX_TYPE_PAY_*|null $orderTxType
     * @param PosInterface::CURRENCY_*|null    $currency
     *
     * @return string
     *
     * @throws \Mews\Pos\Exceptions\UnsupportedTransactionTypeException
     */
    public function mapTxType(string $txType, ?string $paymentModel = null, ?string $orderTxType = null, ?string $currency = null): string
    {
        if (null !== $currency && PosInterface::CURRENCY_TRY !== $currency) {
            return 'TP_Islem_Odeme_WD';
        }

        if (PosInterface::TX_TYPE_CANCEL === $txType && PosInterface::TX_TYPE_PAY_PRE_AUTH === $orderTxType) {
            return 'TP_Islem_Iptal_OnProv';
        }

        return parent::mapTxType($txType, $paymentModel);
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

        return $this->wrapSoapEnvelope(['TP_WMD_Pay' => $requestData], $posAccount);
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
            return $this->create3DHostEnrollmentCheckRequestData($posAccount, $order, $txType);
        }

        if (!$creditCard instanceof \Mews\Pos\Entity\Card\CreditCardInterface) {
            throw new \InvalidArgumentException('Bu işlem için kredi kartı bilgileri gereklidir.');
        }

        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                '@xmlns'             => 'https://turkpos.com.tr/',
                'Islem_Guvenlik_Tip' => $this->secureTypeMappings[$paymentModel],
                'Islem_ID'           => $this->crypt->generateRandomString(),
                'IPAdr'              => (string) $order['ip'],
                'Siparis_ID'         => (string) $order['id'],
                'Islem_Tutar'        => $this->formatAmount($order['amount']),
                'Toplam_Tutar'       => $this->formatAmount($order['amount']),
                'Basarili_URL'       => (string) $order['success_url'],
                'Hata_URL'           => (string) $order['fail_url'],
                'Taksit'             => $this->mapInstallment((int) $order['installment']),
                'KK_Sahibi'          => $creditCard->getHolderName(),
                'KK_No'              => $creditCard->getNumber(),
                'KK_SK_Ay'           => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_MONTH_FORMAT),
                'KK_SK_Yil'          => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_YEAR_FORMAT),
                'KK_CVC'             => $creditCard->getCvv(),
                'KK_Sahibi_GSM'      => '', //optional olmasina ragmen hic gonderilmeyince hata aliniyor.
            ];

        if (PosInterface::CURRENCY_TRY !== $order['currency']) {
            $requestData['Doviz_Kodu'] = $this->mapCurrency($order['currency']);
        }

        $soapAction = $this->mapTxType($txType, $paymentModel, null, $order['currency']);

        $requestData = [$soapAction => $requestData];

        $requestData[$soapAction]['Islem_Hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $this->wrapSoapEnvelope($requestData, $posAccount);
    }

    /**
     * @param AbstractPosAccount          $posAccount
     * @param array<string, mixed>        $order
     * @param PosInterface::TX_TYPE_PAY_* $txType
     *
     * @return array<string, mixed>
     *
     * @throws \Mews\Pos\Exceptions\UnsupportedTransactionTypeException
     */
    public function create3DHostEnrollmentCheckRequestData(AbstractPosAccount $posAccount, array $order, string $txType): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = [
            '@xmlns'           => 'https://turkodeme.com.tr/',
            // Bu alan editable olsun istiyorsanız başına “e|”,
            // readonly olsun istiyorsanız başına “r|” eklemelisiniz.
            'Borclu_Tutar'     => 'r|'.$this->formatAmount($order['amount']),
            'Borclu_Odeme_Tip' => 'r|Diğer',
            'Borclu_AdSoyad'   => 'r|',
            'Borclu_Aciklama'  => 'r|',
            'Return_URL'       => 'r|'.$order['success_url'],
            'Islem_ID'         => $this->crypt->generateRandomString(),
            'Borclu_Kisi_TC'   => '',
            'Terminal_ID'      => $posAccount->getClientId(),
            'Borclu_GSM'       => 'r|',
            // = 0 ise tüm taksitler listelenir. > 0 ise sadece o taksit seçeneği listelenir.
            'Taksit'           => $this->mapInstallment((int) $order['installment']),
        ];

        if (PosInterface::CURRENCY_TRY !== $order['currency']) {
            $requestData['Doviz_Kodu'] = $this->mapCurrency($order['currency']);
        }

        $soapAction = $this->mapTxType($txType, PosInterface::MODEL_3D_HOST);

        return $this->wrapSoapEnvelope([$soapAction => $requestData], $posAccount);
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
                'Islem_Guvenlik_Tip' => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'Islem_ID'           => $this->crypt->generateRandomString(),
                'IPAdr'              => (string) $order['ip'],
                'Siparis_ID'         => (string) $order['id'],
                'Islem_Tutar'        => $this->formatAmount($order['amount']),
                'Toplam_Tutar'       => $this->formatAmount($order['amount']),
                'Taksit'             => $this->mapInstallment((int) $order['installment']),
                'KK_Sahibi'          => $creditCard->getHolderName(),
                'KK_No'              => $creditCard->getNumber(),
                'KK_SK_Ay'           => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_MONTH_FORMAT),
                'KK_SK_Yil'          => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_YEAR_FORMAT),
                'KK_CVC'             => $creditCard->getCvv(),
                'KK_Sahibi_GSM'      => '', //optional olmasina ragmen hic gonderilmeyince hata aliniyor.
            ];

        if (PosInterface::CURRENCY_TRY !== $order['currency']) {
            $requestData['Doviz_Kodu']   = $this->mapCurrency($order['currency']);
            $requestData['Basarili_URL'] = (string) $order['success_url'];
            $requestData['Hata_URL']     = (string) $order['fail_url'];
        }

        $soapAction = $this->mapTxType($txType, PosInterface::MODEL_NON_SECURE, null, $order['currency']);

        if (PosInterface::TX_TYPE_PAY_PRE_AUTH === $txType) {
            $soapAction = $this->mapTxType($txType, PosInterface::MODEL_NON_SECURE);
        }

        $requestData = [$soapAction => $requestData];

        $requestData[$soapAction]['Islem_Hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $this->wrapSoapEnvelope($requestData, $posAccount);
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
                'Prov_Tutar' => $this->formatAmount($order['amount']),
                'Siparis_ID' => (string) $order['id'],
            ];

        return $this->wrapSoapEnvelope([
            $this->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH) => $requestData,
        ], $posAccount);
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
            $this->mapTxType(PosInterface::TX_TYPE_STATUS) => $requestData,
        ], $posAccount);
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
                    'Tutar'      => $order['amount'],
                ];
        }

        return $this->wrapSoapEnvelope([
            $this->mapTxType(PosInterface::TX_TYPE_CANCEL, null, $order['transaction_type']) => $requestData,
        ], $posAccount);
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
                'Tutar'      => $order['amount'],
            ];

        return $this->wrapSoapEnvelope([$this->mapTxType($refundTxType) => $requestData], $posAccount);
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
                'Tarih_Bas' => $this->formatRequestDateTime($order['start_date']),
                'Tarih_Bit' => $this->formatRequestDateTime($order['end_date']),
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
            $this->mapTxType(PosInterface::TX_TYPE_HISTORY) => $requestData,
        ], $posAccount);
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
            if (null === $gatewayURL) {
                throw new \InvalidArgumentException('Please provide $gatewayURL');
            }

            $decoded = \base64_decode($extraData['TO_Pre_Encrypting_OOSResponse']['TO_Pre_Encrypting_OOSResult'], true);
            if (false === $decoded) {
                throw new \RuntimeException($extraData['TO_Pre_Encrypting_OOSResponse']['TO_Pre_Encrypting_OOSResult']);
            }

            $inputs = [
                's' => (string) $extraData['TO_Pre_Encrypting_OOSResponse']['TO_Pre_Encrypting_OOSResult'],
            ];

            return [
                'gateway' => $gatewayURL,
                'method'  => 'GET',
                'inputs'  => $inputs,
            ];
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
        $soapAction               = \array_key_first($requestData);
        $requestData[$soapAction] += $this->getRequestAccountData($posAccount);

        return $this->wrapSoapEnvelope($requestData, $posAccount);
    }

    /**
     * 0 => '1'
     * 1 => '1'
     * 2 => '2'
     * @inheritDoc
     */
    protected function mapInstallment(int $installment): string
    {
        return $installment > 1 ? (string) $installment : '1';
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
     *
     * @return string
     */
    protected function mapCurrency(string $currency): string
    {
        return (string) ($this->currencyMappings[$currency] ?? $currency);
    }

    /**
     * 10.0 => 10,00
     * 1000.5 => 1000,50
     * @param float $amount
     *
     * @return string
     */
    protected function formatAmount(float $amount): string
    {
        return \number_format($amount, 2, ',', '');
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
     * @param \DateTimeInterface $dateTime
     *
     * @return string example 20.11.2018 15:00:00
     */
    private function formatRequestDateTime(\DateTimeInterface $dateTime): string
    {
        return $dateTime->format('d.m.Y H:i:s');
    }

    /**
     * @param array<string, mixed> $data
     * @param AbstractPosAccount   $posAccount
     *
     * @return array{"soap:Body": array<string, mixed>, "soap:Header"?: array<string, mixed>}
     */
    private function wrapSoapEnvelope(array $data, AbstractPosAccount $posAccount): array
    {
        if (isset($data['TO_Pre_Encrypting_OOS'])) {
            return [
                'soap:Header' => [
                    'ServiceSecuritySoapHeader' => [
                        '@xmlns'          => 'https://turkodeme.com.tr/',
                        'CLIENT_CODE'     => $posAccount->getClientId(),
                        'CLIENT_USERNAME' => $posAccount->getUsername(),
                        'CLIENT_PASSWORD' => $posAccount->getPassword(),
                    ],
                ],
                'soap:Body'   => $data,
            ];
        }

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
