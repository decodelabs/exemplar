<?php

/**
 * @package Exemplar
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Exemplar;

use DecodeLabs\Atlas\File;
use DecodeLabs\Exceptional;
use DOMDocument;
use DOMElement;
use ReflectionClass;

/**
 * @phpstan-require-implements Serializable
 */
trait SerializableTrait
{
    /**
     * Create from any xml type
     */
    public static function fromXml(
        mixed $xml
    ): static {
        if ($xml instanceof static) {
            return $xml;
        } elseif ($xml instanceof Provider) {
            return static::fromXmlElement($xml->toXmlElement());
        } elseif ($xml instanceof DOMDocument) {
            return static::fromXmlElement(Element::fromDomDocument($xml));
        } elseif ($xml instanceof DOMElement) {
            return static::fromXmlElement(Element::fromDomElement($xml));
        } elseif ($xml instanceof File) {
            return static::fromXmlFile($xml->getPath());
        } elseif (
            is_string($xml) ||
            (
                is_object($xml) &&
                method_exists($xml, '__toString')
            )
        ) {
            return static::fromXmlString((string)$xml);
        } else {
            throw Exceptional::UnexpectedValue(
                message: 'Unable to convert item to XML Element',
                data: $xml
            );
        }
    }

    /**
     * Load object from xml file
     *
     * @return static
     */
    public static function fromXmlFile(
        string $path
    ): static {
        return static::fromXmlElement(Element::fromXmlFile($path));
    }

    /**
     * Load object from xml string
     *
     * @return static
     */
    public static function fromXmlString(
        string $xml
    ): static {
        return static::fromXmlElement(Element::fromXmlString($xml));
    }

    /**
     * Load object using xmlUnserialize as constructor
     *
     * @return static
     */
    public static function fromXmlElement(
        Element $element
    ): static {
        $class = get_called_class();
        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw Exceptional::Logic(
                message: 'XML consumer cannot be instantiated',
                data: $class
            );
        }

        if (!$ref->implementsInterface(Serializable::class)) {
            throw Exceptional::Logic(
                message: 'XML consumer does not implement DecodeLabs\\Exemplar\\Serializable',
                data: $class
            );
        }

        /** @var static $output */
        $output = $ref->newInstanceWithoutConstructor();
        $output->xmlUnserialize($element);

        return $output;
    }


    /**
     * Convert object to xml string
     */
    public function toXmlString(
        bool $embedded = false
    ): string {
        $writer = Writer::create();

        if (!$embedded) {
            $writer->writeHeader();
        }

        $this->xmlSerialize($writer);
        return $writer->toXmlString($embedded);
    }

    /**
     * Convert object to xml file
     */
    public function toXmlFile(
        string $path
    ): File {
        $writer = Writer::createFile($path);
        $this->xmlSerialize($writer);
        return $writer->toXmlFile($path);
    }

    /**
     * Convert object to XML Element
     */
    public function toXmlElement(): Element
    {
        return Element::fromXmlString($this->toXmlString());
    }
}
