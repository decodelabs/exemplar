<?php

/**
 * @package Exemplar
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Exemplar;

interface Consumer
{
    /**
     * @param mixed $xml
     * @return static
     */
    public static function fromXml($xml): Consumer;

    /**
     * @return static
     */
    public static function fromXmlFile(string $path): Consumer;

    /**
     * @return static
     */
    public static function fromXmlString(string $xml): Consumer;

    /**
     * @return static
     */
    public static function fromXmlElement(Element $element): Consumer;
}
