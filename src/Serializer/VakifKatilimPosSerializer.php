<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNamedNodeMap;
use DOMNodeList;
use Mews\Pos\Gateways\VakifKatilimPos;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Serializer;

class VakifKatilimPosSerializer implements SerializerInterface
{
    use SerializerUtilTrait;

    private Serializer $serializer;

    public function __construct()
    {
        $encoder = new XmlEncoder([
            XmlEncoder::ROOT_NODE_NAME => 'VPosMessageContract',
            XmlEncoder::ENCODING       => 'ISO-8859-1',
        ]);

        $this->serializer = new Serializer([], [$encoder, new JsonEncoder()]);
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return VakifKatilimPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, string $txType): string
    {
        return $this->serializer->encode($data, XmlEncoder::FORMAT);
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data, string $txType): array
    {
        try {
            $data = \str_replace("&#x0;", '', $data);
            $data = \str_replace(' encoding="utf-16"', '', $data);

            return $this->serializer->decode($data, XmlEncoder::FORMAT);
        } catch (NotEncodableValueException $notEncodableValueException) {
            if ($this->isHTML($data)) {
                // 3D form data icin enrollment istegi gonderiyoruz, o istegin cevabi icinde form olan HTML donuyor.
                return $this->transformReceived3DFormData($data);
            }

            throw new \RuntimeException($data, $notEncodableValueException->getCode(), $notEncodableValueException);
        }
    }

    /**
     * Diger Gateway'lerden farkli olarak bu gateway HTML form olan bir response doner.
     * Kutupahenin islem akisina uymasi icin bu HTML form verilerini array'e donusturup, kendimiz post ediyoruz.
     *
     * @param string $response
     *
     * @return array{gateway: string, form_inputs: array<string, string|null>}
     */
    private function transformReceived3DFormData(string $response): array
    {
        $dom = new DOMDocument();
        /**
         * Kuveyt Pos started sending HTML with custom HTML tags such as <APM_DO_NOT_TOUCH>.
         * Without LIBXML_NOERROR flag loadHTML throws "Tag apm_do_not_touch invalid in Entity" exception
         */
        $dom->loadHTML($response, LIBXML_NOERROR);

        $gatewayURL = '';
        /** @var DOMElement|null $formNode */
        $formNode = $dom->getElementsByTagName('form')->item(0);
        if (null === $formNode) {
            throw new \RuntimeException($response, 974);
        }

        /** @var DOMNamedNodeMap $attributes */
        $attributes = $formNode->attributes;
        for ($i = 0; $i < $attributes->length; ++$i) {
            /** @var DOMAttr $attribute */
            $attribute = $attributes->item($i);
            if ('action' === $attribute->name) {
                /**
                 * banka onayladiginda gatewayURL=bankanin gateway url
                 * onaylanmadiginda (hatali istek oldugunda) ise gatewayURL = istekte yer alan failURL
                 */
                $gatewayURL = $attribute->value;
                break;
            }
        }

        $els    = $dom->getElementsByTagName('input');
        $inputs = $this->builtInputsFromHTMLDoc($els);

        return [
            'gateway'     => $gatewayURL,
            'form_inputs' => $inputs,
        ];
    }

    /**
     * html form'da gelen input degeleri array'e donusturur
     *
     * @param DOMNodeList<DOMElement> $domNodeList
     *
     * @return array<string, string|null>
     */
    private function builtInputsFromHTMLDoc(DOMNodeList $domNodeList): array
    {
        $inputs = [];
        foreach ($domNodeList as $el) {
            $key   = null;
            $value = '';

            /** @var DOMNamedNodeMap $attributes */
            $attributes = $el->attributes;
            // for each input element select name and value attribute values
            for ($i = 0; $i < $attributes->length; ++$i) {
                /** @var DOMAttr $attribute */
                $attribute = $attributes->item($i);
                if ('name' === $attribute->name) {
                    /** @var string|null $key */
                    $key = $attribute->value;
                }

                if ('value' === $attribute->name) {
                    /** @var string|null $value */
                    $value = $attribute->value;
                }
            }

            if (!$key) {
                continue;
            }

            if (\in_array($key, ['submit', 'submitBtn'])) {
                continue;
            }

            $inputs[$key] = $value;
        }

        return $inputs;
    }
}
