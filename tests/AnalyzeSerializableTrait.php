<?php

/**
 * @package Exemplar
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Exemplar\Tests;

use DecodeLabs\Exemplar\Element;
use DecodeLabs\Exemplar\Serializable;
use DecodeLabs\Exemplar\SerializableTrait;
use DecodeLabs\Exemplar\Writer;

class AnalyzeSerializableTrait implements Serializable
{
    use SerializableTrait;

    public function xmlUnserialize(
        Element $element
    ): void {
    }

    public function xmlSerialize(
        Writer $writer
    ): void {
    }
}
