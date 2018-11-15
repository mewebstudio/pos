<?php

namespace Mews\Pos;

use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * Trait PosHelpersTrait
 * @package Mews\Pos
 */
trait PosHelpersTrait
{
    /**
     * Create XML DOM Document
     *
     * @param array $nodes
     * @param string $encoding
     * @return string the XML, or false if an error occurred.
     */
    public function createXML(array $nodes, $encoding = 'UTF-8')
    {
        $rootNodeName = array_keys($nodes)[0];
        $encoder = new XmlEncoder($rootNodeName);

        $xml = $encoder->encode($nodes[$rootNodeName], 'xml', [
            'xml_encoding'  => $encoding
        ]);

        return $xml;
    }

    /**
     * Print Data
     *
     * @param $data
     * @return null|string
     */
    public function printData($data)
    {
        if ((is_object($data) || is_array($data)) && !count((array) $data)) {
            $data = null;
        }

        return (string) $data;
    }

    /**
     * Is success
     *
     * @return bool
     */
    public function isSuccess()
    {
        $success = false;
        if (isset($this->response) && $this->response->status == 'approved') {
            $success = true;
        }

        return $success;
    }

    /**
     * Is error
     *
     * @return bool
     */
    public function isError()
    {
        return !$this->isSuccess();
    }
}
