<?php

/**
 * @package Exemplar
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Exemplar;

use DecodeLabs\Atlas\File;

interface Provider
{
    public function toXmlString(bool $embedded = false): string;
    public function toXmlFile(string $path): File;
    public function toXmlElement(): Element;
}
