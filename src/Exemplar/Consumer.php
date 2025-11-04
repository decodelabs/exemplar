<?php

/**
 * Exemplar
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Exemplar;

interface Consumer
{
    public static function fromXml(
        mixed $xml
    ): static;

    public static function fromXmlFile(
        string $path
    ): static;

    public static function fromXmlString(
        string $xml
    ): static;

    public static function fromXmlElement(
        Element $element
    ): static;
}
