<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * A normalizer that adds a specified XML prefix to the keys of an array during normalization.
 *
 * The class provides functionality to normalize data arrays by adding a given prefix to each key.
 * It supports normalization only when the format is "xml" and a prefix is defined in the context.
 */
class XmlPrefixNormalizer implements NormalizerInterface
{
    /**
     * @param array<string, mixed> $data
     * @param string|null          $format
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize($data, $format = null, array $context = []): array
    {
        $prefix = $context['xml_prefix'];

        return $this->addPrefix($data, $prefix);
    }

    /**
     * @param mixed                $data
     * @param string|null          $format
     * @param array<string, mixed> $context
     *
     * @return bool
     */
    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return 'xml' === $format && isset($context['xml_prefix']) && \is_array($data);
    }

    /**
     * @param string|null $format
     *
     * @return array<string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return ['*' => true];
    }

    /**
     * @param iterable<string, mixed> $data
     * @param string                  $prefix
     *
     * @return array<string, mixed>
     */
    private function addPrefix(iterable $data, string $prefix): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $value = $this->addPrefix($value, $prefix);
            }
            $normalized[$prefix.':'.$key] = $value;
        }

        return $normalized;
    }
}
