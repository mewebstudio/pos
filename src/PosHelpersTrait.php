<?php

namespace Mews\Pos;

use DOMDocument;

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
     * @return string
     */
    protected function createXML(array $nodes)
    {
        $dom = new DOMDocument('1.0', 'ISO-8859-9');
        $root = $dom->createElement('CC5Request');

        if (count($nodes)) {
            foreach ($nodes as $key => $val) {
                if (is_array($val)) {
                    $child = $dom->createElement($key);

                    if (count($val)) {
                        foreach ($val as $_key => $_val) {
                            $_child = $dom->createElement($_key, $_val);
                            $child->appendChild($_child);
                        }
                    }
                } else {
                    $child = $dom->createElement($key, $val);
                }

                $root->appendChild($child);
            }
        }

        $dom->appendChild($root);

        return $dom->saveXML();
    }

    /**
     * Print Data
     *
     * @param $data
     * @return null|string
     */
    protected function printData($data)
    {
        if ((is_object($data) || is_array($data)) && !count((array) $data)) {
            $data = null;
        }

        return (string) $data;
    }
}
