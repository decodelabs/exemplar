<?php

/**
 * @package Exemplar
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Exemplar;

use ArrayAccess;
use DecodeLabs\Atlas;
use DecodeLabs\Atlas\File;
use DecodeLabs\Collections\AttributeContainer;
use DecodeLabs\Collections\AttributeContainerTrait;
use DecodeLabs\Elementary\Markup;
use DecodeLabs\Exceptional;
use DecodeLabs\Glitch\Dumpable;
use ErrorException;
use Throwable;
use XMLWriter;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Writer implements
    Markup,
    Provider,
    AttributeContainer,
    ArrayAccess,
    Dumpable
{
    public const ELEMENT = 1;
    public const CDATA = 2;
    public const CDATA_ELEMENT = 3;
    public const COMMENT = 4;
    public const PI = 5;

    use AttributeContainerTrait;

    /**
     * @var XMLWriter
     */
    protected $document;

    /**
     * @var string|null
     */
    protected $path;


    /**
     * @var bool
     */
    protected $headerWritten = false;

    /**
     * @var bool
     */
    protected $dtdWritten = false;

    /**
     * @var bool
     */
    protected $rootWritten = false;

    /**
     * @var bool
     */
    protected $finalized = false;


    /**
     * @var string|null
     */
    protected $elementContent = null;

    /**
     * @var array<string>
     */
    protected $rawAttributeNames = [];

    /**
     * @var int|null
     */
    protected $currentNode = null;



    /**
     * Create file writer
     */
    public static function createFile(string $path): Writer
    {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $document = new XMLWriter();
        $document->openURI($path);

        return new self($document, $path);
    }

    /**
     * Create writer in memory
     */
    public static function create(): Writer
    {
        return new self();
    }

    /**
     * Init with optional file path
     */
    protected function __construct(XMLWriter $document = null, ?string $path = null)
    {
        if ($document === null) {
            $document = new XMLWriter();
            $document->openMemory();
        }

        $this->path = $path;

        try {
            $document->outputMemory(false);
        } catch (Throwable $e) {
            $document->openMemory();
        }

        $this->document = $document;

        $this->document->setIndent(true);
        $this->document->setIndentString('    ');
    }


    /**
     * Get raw XMLWriter document
     */
    public function getDocument(): XMLWriter
    {
        return $this->document;
    }

    /**
     * Get active file path
     */
    public function getFilePath(): ?string
    {
        return $this->path;
    }


    /**
     * Write initial XML header
     */
    public function writeHeader(string $version = '1.0', string $encoding = 'UTF-8', ?bool $standalone = null): Writer
    {
        if ($this->headerWritten) {
            throw Exceptional::Logic('XML header has already been written');
        }

        if ($this->dtdWritten || $this->rootWritten) {
            throw Exceptional::Logic('XML header cannot be written once the document is open');
        }

        try {
            if ($standalone !== null) {
                $standalone = $standalone ? 'yes' : 'no';
            }

            $this->document->startDocument($version, $encoding, $standalone);
        } catch (ErrorException $e) {
            throw Exceptional::InvalidArguement($e->getMessage(), [
                'previous' => $e
            ]);
        }

        $this->headerWritten = true;
        return $this;
    }

    /**
     * Write full DTD
     */
    public function writeDtd(string $name, string $publicId = null, string $systemId = null, string $subset = null): Writer
    {
        if ($this->rootWritten) {
            throw Exceptional::Logic('XML DTD cannot be written once the document is open');
        }

        if (!$this->headerWritten) {
            $this->writeHeader();
        }

        try {
            $this->document->writeDtd($name, $publicId, $systemId, $subset);
        } catch (ErrorException $e) {
            throw Exceptional::InvalidArguement($e->getMessage(), [
                'previous' => $e
            ]);
        }

        $this->dtdWritten = true;
        return $this;
    }

    /**
     * Write DTD attlist
     */
    public function writeDtdAttlist(string $name, string $content): Writer
    {
        if ($this->rootWritten) {
            throw Exceptional::Logic('XML DTD cannot be written once the document is open');
        }

        if (!$this->headerWritten) {
            $this->writeHeader();
        }

        try {
            $this->document->writeDtdAttlist($name, $content);
        } catch (ErrorException $e) {
            throw Exceptional::InvalidArguement($e->getMessage(), [
                'previous' => $e
            ]);
        }

        $this->dtdWritten = true;
        return $this;
    }

    /**
     * Write DTD element
     */
    public function writeDtdElement(string $name, string $content): Writer
    {
        if ($this->rootWritten) {
            throw Exceptional::Logic('XML DTD cannot be written once the document is open');
        }

        if (!$this->headerWritten) {
            $this->writeHeader();
        }

        try {
            $this->document->writeDtdElement($name, $content);
        } catch (ErrorException $e) {
            throw Exceptional::InvalidArguement($e->getMessage(), [
                'previous' => $e
            ]);
        }

        $this->dtdWritten = true;
        return $this;
    }

    /**
     * Write DTD entity
     */
    public function writeDtdEntity(string $name, string $content, bool $isParam, string $publicId, string $systemId, string $nDataId): Writer
    {
        if ($this->rootWritten) {
            throw Exceptional::Logic('XML DTD cannot be written once the document is open');
        }

        if (!$this->headerWritten) {
            $this->writeHeader();
        }

        try {
            $this->document->writeDtdEntity($name, $content, $isParam, $publicId, $systemId, $nDataId);
        } catch (ErrorException $e) {
            throw Exceptional::InvalidArguement($e->getMessage(), [
                'previous' => $e
            ]);
        }

        $this->dtdWritten = true;
        return $this;
    }


    /**
     * Shortcut to writeElement
     *
     * @param array<mixed> $args
     */
    public function __call(string $method, array $args): Writer
    {
        return $this->writeElement($method, ...$args);
    }


    /**
     * Write full element in one go
     *
     * @param mixed $content
     * @param array<string, mixed>|null $attributes
     */
    public function writeElement(string $name, $content = null, array $attributes = null): Writer
    {
        $this->startElement($name, $attributes);

        if ($content !== null) {
            $this->setElementContent($content);
        }

        return $this->endElement();
    }

    /**
     * Open element to write into
     *
     * @param array<string, mixed>|null $attributes
     */
    public function startElement(string $name, array $attributes = null): Writer
    {
        $this->completeCurrentNode();

        if ($attributes === null) {
            $attributes = [];
        }

        $origName = $name;

        if (false !== strpos($name, '[')) {
            $name = preg_replace_callback('/\[([^\]]*)\]/', function ($res) use (&$attributes) {
                $parts = explode('=', $res[1], 2);

                if (empty($key = array_shift($parts))) {
                    throw Exceptional::UnexpectedValue('Invalid tag attribute definition', null, $res);
                }

                $value = (string)array_shift($parts);
                $first = substr($value, 0, 1);
                $last = substr($value, -1);

                if (strlen($value) > 1
                && (($first == '"' && $last == '"')
                || ($first == "'" && $last == "'"))) {
                    $value = substr($value, 1, -1);
                }

                $attributes[$key] = $value;
                return '';
            }, $name) ?? $name;
        }

        if (false !== strpos($name, '#')) {
            $name = preg_replace_callback('/\#([^ .\[\]]+)/', function ($res) use (&$attributes) {
                $attributes['id'] = $res[1];
                return '';
            }, $name) ?? $name;
        }

        $parts = explode('.', $name);

        if (empty($name = array_shift($parts))) {
            throw Exceptional::UnexpectedValue(
                'Unable to parse tag class definition',
                null,
                $origName
            );
        }

        if (!empty($parts)) {
            $attributes['class'] = implode(' ', $parts);
        }

        $cdata = false;

        if (substr($name, 0, 1) === '@') {
            $cdata = true;
            $name = substr($name, 1);
        }

        $this->document->startElement($name);
        $this->currentNode = self::ELEMENT;

        if ($cdata) {
            $this->currentNode = self::CDATA_ELEMENT;
        }

        if (!empty($attributes)) {
            $this->setAttributes($attributes);
        }

        $this->rootWritten = true;

        return $this;
    }

    /**
     * Complete writing current element
     */
    public function endElement(): Writer
    {
        if ($this->currentNode === self::CDATA) {
            $this->completeCurrentNode();
        }

        if (
            $this->currentNode !== self::ELEMENT &&
            $this->currentNode !== self::CDATA_ELEMENT
        ) {
            throw Exceptional::Logic('XML writer is not currently writing an element');
        }

        $this->completeCurrentNode();

        if ($this->currentNode === self::CDATA_ELEMENT) {
            $this->document->endCData();
        }

        $this->document->endElement();
        $this->currentNode = self::ELEMENT;

        return $this;
    }

    /**
     * Store element content ready for writing
     *
     * @param mixed $content
     */
    public function setElementContent($content): Writer
    {
        $this->elementContent = $this->renderContent($content);
        return $this;
    }

    /**
     * Render element content to string
     *
     * @param mixed $content
     */
    protected function renderContent($content): ?string
    {
        if (is_callable($content) && is_object($content)) {
            return $this->renderContent($content($this));
        }

        if (is_iterable($content) && !$content instanceof Markup) {
            $this->completeCurrentNode();

            foreach ($content as $part) {
                $this->document->text((string)$this->renderContent($part));
            }

            return null;
        }

        return (string)$content;
    }

    /**
     * Get current buffered element content
     */
    public function getElementContent(): ?string
    {
        return $this->elementContent;
    }



    /**
     * Write a full CDATA section
     */
    public function writeCData(?string $content): Writer
    {
        $this->startCData();
        $this->writeCDataContent((string)$content);
        return $this->endCData();
    }

    /**
     * Write new element with CDATA section
     *
     * @param array<string, mixed>|null $attributes
     */
    public function writeCDataElement(string $name, ?string $content, array $attributes = null): Writer
    {
        $this->startElement($name, $attributes);
        $this->writeCData($content);
        return $this->endElement();
    }

    /**
     * Start new CDATA section
     */
    public function startCData(): Writer
    {
        $this->completeCurrentNode();
        $this->document->startCData();
        $this->currentNode = self::CDATA;
        return $this;
    }

    /**
     * Write content for CDATA section
     */
    public function writeCDataContent(?string $content): Writer
    {
        if ($this->currentNode !== self::CDATA) {
            throw Exceptional::Logic('XML writer is not currently writing CDATA');
        }

        $content = self::normalizeString($content);
        $this->document->text($content);
        return $this;
    }

    /**
     * Finalize CDATA section
     */
    public function endCData(): Writer
    {
        if ($this->currentNode !== self::CDATA) {
            throw Exceptional::Logic('XML writer is not current writing CDATA');
        }

        $this->document->endCData();
        $this->currentNode = self::ELEMENT;
        return $this;
    }


    /**
     * Write comment in one go
     */
    public function writeComment(?string $comment): Writer
    {
        $this->startComment();
        $this->writeCommentContent($comment);
        return $this->endComment();
    }

    /**
     * Begin comment node
     */
    public function startComment(): Writer
    {
        $this->completeCurrentNode();
        $this->document->startComment();
        $this->currentNode = self::COMMENT;
        return $this;
    }

    /**
     * Write comment body
     */
    public function writeCommentContent(?string $comment): Writer
    {
        if ($this->currentNode !== self::COMMENT) {
            throw Exceptional::Logic('XML writer is not currently writing a comment');
        }

        $comment = self::normalizeString($comment);
        $this->document->text($comment);
        return $this;
    }

    /**
     * Finalize comment node
     */
    public function endComment(): Writer
    {
        if ($this->currentNode !== self::COMMENT) {
            throw Exceptional::Logic('XML writer is not currently writing a comment');
        }

        $this->document->endComment();
        $this->currentNode = self::ELEMENT;
        return $this;
    }


    /**
     * Write PI in one go
     */
    public function writePi(string $target, ?string $content): Writer
    {
        $this->startPi($target);
        $this->writePiContent($content);
        return $this->endPi();
    }

    /**
     * Begin PI node
     */
    public function startPi(string $target): Writer
    {
        $this->completeCurrentNode();
        $this->document->startPI($target);
        $this->currentNode = self::PI;
        return $this;
    }

    /**
     * Write PI content
     */
    public function writePiContent(?string $content): Writer
    {
        if ($this->currentNode !== self::PI) {
            throw Exceptional::Logic(
                'XML writer is not currently writing a processing instruction'
            );
        }

        $this->document->text((string)$content);
        return $this;
    }

    /**
     * Finalize PI
     */
    public function endPi(): Writer
    {
        if ($this->currentNode !== self::PI) {
            throw Exceptional::Logic(
                'XML writer is not currently writing a processing instruction'
            );
        }

        $this->document->endPI();
        $this->currentNode = self::ELEMENT;
        return $this;
    }



    /**
     * Set list of attribute names to be written raw
     */
    public function setRawAttributeNames(string ...$names): Writer
    {
        $this->rawAttributeNames = $names;
        return $this;
    }

    /**
     * Get list of attributes to be written raw
     *
     * @return array<string>
     */
    public function getRawAttributeNames(): array
    {
        return $this->rawAttributeNames;
    }



    /**
     * Write directly to XML buffer
     */
    public function writeRaw(?string $content): Writer
    {
        $this->document->writeRaw((string)$content);
        return $this;
    }


    /**
     * Write stored info to doc
     */
    protected function completeCurrentNode(): void
    {
        switch ($this->currentNode) {
            case self::ELEMENT:
            case self::CDATA_ELEMENT:
                foreach ($this->attributes as $key => $value) {
                    if (is_bool($value)) {
                        $value = $value ? 'true' : 'false';
                    }

                    if (in_array($key, $this->rawAttributeNames)) {
                        $this->document->startAttribute($key);
                        $this->document->writeRaw($value);
                        $this->document->endAttribute();
                    } else {
                        $this->document->writeAttribute($key, (string)$value);
                    }
                }

                $this->attributes = [];
                $this->rawAttributeNames = [];

                if ($this->currentNode === self::CDATA_ELEMENT) {
                    $this->document->startCData();
                }

                if ($this->elementContent !== null) {
                    $content = self::normalizeString($this->elementContent);
                    $this->document->text($content);
                    $this->elementContent = null;
                }

                break;

            case self::CDATA:
                $this->endCData();
                break;

            case self::COMMENT:
                $this->endComment();
                break;

            case self::PI:
                $this->endPi();
                break;
        }
    }


    /**
     * Ensure everything is written to buffer
     */
    public function finalize(): Writer
    {
        if ($this->finalized) {
            return $this;
        }

        $this->completeCurrentNode();

        if ($this->headerWritten) {
            $this->document->endDocument();
        }

        if ($this->path) {
            $this->document->flush();
        }

        $this->finalized = true;
        return $this;
    }

    /**
     * Convert to string
     */
    public function toXmlString(bool $embedded = false): string
    {
        $this->finalize();
        $string = $this->__toString();

        if (!$embedded || !$this->headerWritten) {
            return $string;
        }

        $element = Element::fromString($string);
        return $element->__toString();
    }

    /**
     * Export to file
     */
    public function toXmlFile(string $path): File
    {
        if (!class_exists(Atlas::class)) {
            throw Exceptional::ComponentUnavailable(
                'Saving XML to file requires DecodeLabs Atlas'
            );
        }

        $this->finalize();

        if ($path === $this->path) {
            return Atlas::$fs->file($this->path);
        }

        if ($this->path !== null) {
            return Atlas::$fs->copyFile($this->path, $path);
        }

        return Atlas::$fs->createFile($path, $this->__toString());
    }

    /**
     * Convert to Element instance
     */
    public function toXmlElement(): Element
    {
        $this->finalize();

        if ($this->path !== null) {
            return Element::fromXmlFile($this->path);
        } else {
            return Element::fromXmlString($this->__toString());
        }
    }

    /**
     * Import XML string from reader node
     *
     * @return $this
     */
    public function importXmlElement(Element $element): Writer
    {
        $this->completeCurrentNode();
        $this->document->writeRaw("\n" . $element->__toString() . "\n");
        return $this;
    }

    /**
     * Normalize string for writing
     */
    protected static function normalizeString(?string $string): string
    {
        return (string)preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', (string)$string);
    }

    /**
     * Convert to string
     */
    public function __toString(): string
    {
        if ($this->path !== null) {
            $this->document->flush();

            if (false === ($output = file_get_contents($this->path))) {
                throw Exceptional::UnexpectedValue(
                    'Unable to read contents of file',
                    null,
                    $this->path
                );
            }

            return $output;
        } else {
            return $this->document->outputMemory();
        }
    }


    /**
     * Shortcut to set attribute
     *
     * @param string $key
     * @param mixed $value
     */
    public function offsetSet($key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Shortcut to get attribute
     *
     * @param string $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Shortcut to test for attribute
     *
     * @param string $key
     */
    public function offsetExists($key): bool
    {
        return $this->hasAttribute($key);
    }

    /**
     * Shortcut to remove attribute
     *
     * @param string $key
     */
    public function offsetUnset($key): void
    {
        $this->removeAttribute($key);
    }

    /**
     * Dump string
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'xml' => $this->__toString()
        ];
    }

    /**
     * Export for dump inspection
     *
     * @return iterable<string, mixed>
     */
    public function glitchDump(): iterable
    {
        yield 'text' => $this->__toString();

        if ($this->path !== null) {
            yield 'property:*path' => $this->path;
        }
    }
}
