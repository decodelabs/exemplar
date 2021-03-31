<?php

/**
 * @package Exemplar
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Exemplar;

interface Consumer
{
    public static function fromXml($xml);
    public static function fromXmlFile(string $path);
    public static function fromXmlString(string $xml);
    public static function fromXmlElement(Element $element);
}
