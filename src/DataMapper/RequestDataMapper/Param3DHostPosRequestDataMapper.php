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
use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for Param3DHostPos Gateway requests
 */
class Param3DHostPosRequestDataMapper extends AbstractRequestDataMapper
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
        return Param3DHostPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * @param ParamPosAccount                      $posAccount
     * @param array<string, int|string|float|null> $order
     * @param PosInterface::TX_TYPE_PAY_*          $txType
     *
     * @return array<string, mixed>
     */
    public function create3DEnrollmentCheckRequestData(AbstractPosAccount $posAccount, array $order, string $txType): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = [
            '@xmlns'           => 'https://turkodeme.com.tr/',
            // Bu alan editable olsun istiyorsanız başına “e|”,
            // readonly olsun istiyorsanız başına “r|” eklemelisiniz.
            'Borclu_Tutar'     => 'r|'.$this->valueFormatter->formatAmount($order['amount'], $txType),
            'Borclu_Odeme_Tip' => 'r|Diğer',
            'Borclu_AdSoyad'   => 'r|',
            'Borclu_Aciklama'  => 'r|',
            'Return_URL'       => 'r|'.$order['success_url'],
            'Islem_ID'         => $this->crypt->generateRandomString(),
            'Borclu_Kisi_TC'   => '',
            'Terminal_ID'      => $posAccount->getClientId(),
            'Borclu_GSM'       => 'r|',
            // = 0 ise tüm taksitler listelenir. > 0 ise sadece o taksit seçeneği listelenir.
            'Taksit'           => $this->valueFormatter->formatInstallment((int) $order['installment']),
        ];

        if (PosInterface::CURRENCY_TRY !== $order['currency']) {
            $requestData['Doviz_Kodu'] = $this->valueMapper->mapCurrency($order['currency']);
        }

        $soapAction = $this->valueMapper->mapTxType($txType, PosInterface::MODEL_3D_HOST);

        return $this->wrapSoapEnvelope([$soapAction => $requestData], $posAccount);
    }

    /**
     * {@inheritDoc}
     * @return array<string, string|array<string, string>>
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }


    /**
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        throw new NotImplementedException();
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
        throw new NotImplementedException();
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
        if (PosInterface::MODEL_3D_HOST !== $paymentModel) {
            throw new \InvalidArgumentException();
        }
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

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        throw new NotImplementedException();
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
     * @param array<string, mixed> $data
     * @param AbstractPosAccount   $posAccount
     *
     * @return array{"soap:Body": array<string, mixed>, "soap:Header"?: array<string, mixed>}
     */
    private function wrapSoapEnvelope(array $data, AbstractPosAccount $posAccount): array
    {
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
}
