<?php

/**
 * @package Exemplar
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Exemplar;

use ArrayAccess;
use Countable;

use DecodeLabs\Atlas;
use DecodeLabs\Atlas\File;
use DecodeLabs\Coercion;
use DecodeLabs\Collections\AttributeContainer;
use DecodeLabs\Elementary\Markup;
use DecodeLabs\Exceptional;

use DOMAttr;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Throwable;
use Traversable;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Element implements
    Markup,
    Consumer,
    Provider,
    AttributeContainer,
    Countable,
    ArrayAccess
{
    /**
     * @var DOMElement
     */
    protected $element;

    /**
     * Create from any xml type
     */
    public static function fromXml(
        mixed $xml
    ): static {
        if ($xml instanceof static) {
            return $xml;
        } elseif ($xml instanceof Provider) {
            $output = $xml->toXmlElement();

            if (!$output instanceof static) {
                $output = new static($output->getDomElement());
            }

            return $output;
        } elseif ($xml instanceof DOMDocument) {
            return static::fromDomDocument($xml);
        } elseif ($xml instanceof DOMElement) {
            return static::fromDomElement($xml);
        } elseif (
            interface_exists(File::class) &&
            $xml instanceof File
        ) {
            return static::fromFile($xml->getPath());
        } elseif (
            is_string($xml) || (
                is_object($xml) &&
                method_exists($xml, '__toString')
            )
        ) {
            return static::fromXmlString((string)$xml);
        } else {
            throw Exceptional::UnexpectedValue(
                'Unable to convert item to XML Element',
                null,
                $xml
            );
        }
    }


    /**
     * Create instance from file
     */
    public static function fromFile(
        string $path
    ): static {
        $extension = strtolower((string)pathinfo($path, \PATHINFO_EXTENSION));

        if ($extension === 'html' || $extension === 'htm') {
            return static::fromHtmlFile($path);
        }

        return static::fromXmlFile($path);
    }

    /**
     * Create instance from XML file
     */
    public static function fromXmlFile(
        string $path
    ): static {
        try {
            $document = static::newDomDocument();
            $document->load($path);
        } catch (Throwable $e) {
            throw Exceptional::Io('Unable to load XML file', [
                'previous' => $e
            ]);
        }

        return static::fromDomDocument($document);
    }

    /**
     * Create instance from string
     */
    public static function fromString(
        string $xml
    ): static {
        if (preg_match('/^\<\!DOCTYPE html\>/', $xml)) {
            return static::fromHtmlString($xml);
        }

        return static::fromXmlString($xml);
    }

    /**
     * Create instance from XML string
     */
    public static function fromXmlString(
        string $xml
    ): static {
        $xml = trim($xml);

        if (!stristr($xml, '<?xml')) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $xml;
        }

        $xml = static::normalizeString($xml);

        try {
            $document = static::newDOMDocument();
            $document->loadXML($xml);
        } catch (Throwable $e) {
            throw Exceptional::Io('Unable to load XML string', [
                'previous' => $e
            ]);
        }

        return static::fromDOMDocument($document);
    }

    /**
     * Create HTML instance from file
     */
    public static function fromHtmlFile(
        string $path
    ): static {
        try {
            $document = static::newDomDocument();
            $document->loadHtmlFile($path);
        } catch (Throwable $e) {
            throw Exceptional::Io('Unable to load HTML file', [
                'previous' => $e
            ]);
        }

        return static::fromDomDocument($document);
    }

    /**
     * Create instance from string
     */
    public static function fromHtmlString(
        string $xml
    ): static {
        try {
            $document = static::newDomDocument();
            $document->loadHTML($xml);
        } catch (Throwable $e) {
            throw Exceptional::Io('Unable to load HTML string', [
                'previous' => $e
            ]);
        }

        return static::fromDomDocument($document);
    }

    /**
     * Passthrough
     */
    public static function fromXmlElement(
        Element $element
    ): static {
        if (!$element instanceof static) {
            $element = new static($element->getDomElement());
        }

        return $element;
    }

    /**
     * Create instance from DOMDocument
     */
    public static function fromDomDocument(
        DOMDocument $document
    ): static {
        $document->formatOutput = true;

        if ($document->documentElement === null) {
            throw Exceptional::UnexpectedValue('Document has no documentElement', null, $document);
        }

        return static::wrapDomNode($document->documentElement);
    }

    /**
     * Create instance from DOMElement
     */
    public static function fromDomElement(
        DOMElement $element
    ): static {
        self::extractOwnerDocument($element)->formatOutput = true;
        return static::wrapDomNode($element);
    }

    /**
     * Create a new DOMDocument
     */
    protected static function newDomDocument(): DOMDocument
    {
        $output = new DOMDocument();
        $output->formatOutput = true;
        return $output;
    }


    /**
     * Get element owner document
     */
    protected static function extractOwnerDocument(
        DOMElement $element
    ): DOMDocument {
        if ($element->ownerDocument === null) {
            throw Exceptional::UnexpectedValue('Element has no ownerDocument', null, $element);
        }

        return $element->ownerDocument;
    }



    /**
     * Init with DOMElement
     */
    final public function __construct(
        DOMElement $element
    ) {
        $this->element = $element;
    }


    /**
     * Replace this node element with a new tag
     *
     * @return $this
     */
    public function setTagName(
        string $name
    ): static {
        $document = $this->getDomDocument();
        $newNode = $document->createElement($name);
        $children = [];

        foreach ($this->element->childNodes as $child) {
            /** @var DOMNode $child */
            $children[] = $document->importNode($child, true);
        }

        foreach ($children as $child) {
            $newNode->appendChild($child);
        }

        foreach ($this->element->attributes ?? [] as $attrNode) {
            /** @var DOMAttr $attrNode */
            $document->importNode($attrNode, true);
            $newNode->setAttributeNode($attrNode);
        }

        $this->getParentDomElement()->replaceChild($newNode, $this->element);
        $this->element = $newNode;

        return $this;
    }

    /**
     * Get tag name of node
     */
    public function getTagName(): string
    {
        return $this->element->nodeName;
    }


    /**
     * Merge attributes on node
     *
     * @param array<string, mixed> $attributes
     * @return $this
     */
    public function setAttributes(
        array $attributes
    ): static {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Replace attribute on node
     *
     * @param array<string, mixed> $attributes
     * @return $this
     */
    public function replaceAttributes(
        array $attributes
    ): static {
        return $this->clearAttributes()->setAttributes($attributes);
    }

    /**
     * Set attribute on node
     *
     * @return $this
     */
    public function setAttribute(
        string $key,
        mixed $value
    ): static {
        $this->element->setAttribute(
            $key,
            Coercion::forceString($value)
        );
        return $this;
    }

    /**
     * Get all attribute values
     *
     * @return array<string, string|null>
     */
    public function getAttributes(): array
    {
        $output = [];

        foreach ($this->element->attributes ?? [] as $attrNode) {
            /** @var DOMAttr $attrNode */
            $output[(string)$attrNode->name] = $attrNode->value;
        }

        return $output;
    }

    /**
     * Get single attribute value
     */
    public function getAttribute(
        string $key
    ): ?string {
        $output = $this->element->getAttribute($key);

        if (!strlen($output)) {
            $output = null;
        }

        return $output;
    }

    /**
     * Convert attribute to boolean
     */
    public function getBooleanAttribute(
        string $name
    ): bool {
        switch ($text = strtolower(trim((string)$this->getAttribute($name)))) {
            case 'false':
            case '0':
            case 'no':
            case 'n':
            case 'off':
            case 'disabled':
                return false;

            case 'true':
            case '1':
            case 'yes':
            case 'y':
            case 'on':
            case 'enabled':
                return true;
        }

        if (is_numeric($text)) {
            return (int)$text > 0;
        }

        return (bool)$text;
    }

    /**
     * Remove attribute list
     *
     * @return $this
     */
    public function removeAttribute(
        string ...$keys
    ): static {
        foreach ($keys as $key) {
            $this->element->removeAttribute($key);
        }

        return $this;
    }

    /**
     * Does node have attribute?
     */
    public function hasAttribute(
        string ...$keys
    ): bool {
        foreach ($keys as $key) {
            if ($this->element->hasAttribute($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does node have attributes?
     */
    public function hasAttributes(
        string ...$keys
    ): bool {
        foreach ($keys as $key) {
            if (!$this->element->hasAttribute($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * How many attributes?
     */
    public function countAttributes(): int
    {
        /** @phpstan-ignore-next-line */
        if ($this->element->attributes === null) {
            return 0;
        }

        return $this->element->attributes->count();
    }

    /**
     * Remove all attributes
     *
     * @return $this
     */
    public function clearAttributes(): static
    {
        foreach ($this->element->attributes ?? [] as $attrNode) {
            /** @var DOMAttr $attrNode */
            $this->element->removeAttribute($attrNode->name);
        }

        return $this;
    }




    /**
     * Set inner XML string
     *
     * @return $this
     */
    public function setInnerXml(
        string $inner
    ): static {
        $this->removeAllChildren();

        $fragment = $this->getDomDocument()->createDocumentFragment();
        $fragment->appendXml($inner);
        $this->element->appendChild($fragment);

        return $this;
    }

    /**
     * Get string of all child nodes
     */
    public function getInnerXml(): string
    {
        $output = '';

        foreach ($this->element->childNodes as $child) {
            /** @var DOMNode $child */
            $output .= $this->getDomDocument()->saveXML($child);
        }

        return $output;
    }

    /**
     * Normalize inner xml string
     */
    public function getComposedInnerXml(): string
    {
        $output = $this->getInnerXml();
        $output = (string)preg_replace('/  +/', ' ', $output);
        $output = str_replace(["\r", "\n\n", "\n "], ["\n", "\n", "\n"], $output);
        return trim($output);
    }


    /**
     * Replace contents with text
     *
     * @return $this
     */
    public function setTextContent(
        string $content
    ): static {
        $this->removeAllChildren();

        $text = $this->getDomDocument()->createTextNode($content);
        $this->element->appendChild($text);

        return $this;
    }

    /**
     * Get all text content in node
     */
    public function getTextContent(): string
    {
        return $this->element->textContent;
    }

    /**
     * Get ALL normalized text in node
     */
    public function getComposedTextContent(): string
    {
        $isRoot = $this->element === $this->getDomDocument()->documentElement;
        $output = '';

        /** @var DOMNode $node */
        foreach ($this->element->childNodes as $node) {
            $value = null;

            switch ($node->nodeType) {
                case \XML_ELEMENT_NODE:
                    $value = $this->wrapDomNode($node)->getComposedTextContent();

                    if ($isRoot) {
                        $value .= "\n";
                    }

                    break;

                case \XML_TEXT_NODE:
                    $value = ltrim((string)$node->nodeValue);

                    if ($value != $node->nodeValue) {
                        $value = ' ' . $value;
                    }

                    $t = rtrim($value);

                    if ($t != $value) {
                        $value = $t . ' ';
                    }

                    break;

                case \XML_CDATA_SECTION_NODE:
                    $value = trim((string)$node->nodeValue) . "\n";
                    break;
            }

            if (!empty($value)) {
                $output .= $value;
            }
        }

        return trim(str_replace(['  ', "\n "], [' ', "\n"], $output));
    }


    /**
     * Replace node content with CDATA
     *
     * @return $this
     */
    public function setCDataContent(
        string $content
    ): static {
        $this->removeAllChildren();

        $content = $this->getDomDocument()->createCDataSection($content);
        $this->element->appendChild($content);

        return $this;
    }

    /**
     * Add CDATA section to end of node
     *
     * @return $this
     */
    public function prependCDataContent(
        string $content
    ): static {
        $content = $this->getDomDocument()->createCDataSection($content);

        if ($this->element->firstChild !== null) {
            $this->element->insertBefore($content, $this->element->firstChild);
        } else {
            $this->element->appendChild($content);
        }

        return $this;
    }

    /**
     * Add CDATA section to start of node
     *
     * @return $this
     */
    public function appendCDataContent(
        string $content
    ): static {
        $content = $this->getDomDocument()->createCDataSection($content);
        $this->element->appendChild($content);

        return $this;
    }

    /**
     * Get first CDATA section
     */
    public function getFirstCDataSection(): ?string
    {
        foreach ($this->element->childNodes as $node) {
            /** @var DOMNode $node */
            if ($node->nodeType == \XML_CDATA_SECTION_NODE) {
                return $node->nodeValue;
            }
        }

        return null;
    }

    /**
     * Scan all CDATA sections within node
     *
     * @return Traversable<string>
     */
    public function scanAllCDataSections(): Traversable
    {
        foreach ($this->element->childNodes as $node) {
            /** @var DOMNode $node */
            if ($node->nodeType == \XML_CDATA_SECTION_NODE) {
                yield (string)$node->nodeValue;
            }
        }
    }

    /**
     * Get all CDATA sections within node
     *
     * @return array<string>
     */
    public function getAllCDataSections(): array
    {
        return iterator_to_array($this->scanAllCDataSections());
    }


    /**
     * Count all child elements
     */
    public function count(): int
    {
        $output = 0;

        foreach ($this->element->childNodes as $node) {
            /** @var DOMNode $node */
            if ($node->nodeType == \XML_ELEMENT_NODE) {
                $output++;
            }
        }

        return $output;
    }

    /**
     * Count child elements of type
     */
    public function countType(
        string $name
    ): int {
        $output = 0;

        foreach ($this->element->childNodes as $node) {
            /** @var DOMNode $node */
            if (
                $node->nodeType == \XML_ELEMENT_NODE &&
                $node->nodeName == $name
            ) {
                $output++;
            }
        }

        return $output;
    }

    /**
     * Does this node have any children?
     */
    public function hasChildren(): bool
    {
        if (!$this->element->childNodes->length) {
            return false;
        }

        foreach ($this->element->childNodes as $node) {
            /** @var DOMNode $node */
            if ($node->nodeType == \XML_ELEMENT_NODE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get list of elements of type
     *
     * @return array<static>
     */
    public function __get(
        string $name
    ): array {
        return iterator_to_array($this->scanChildList($name));
    }


    /**
     * Scan all child elements
     *
     * @return Traversable<static>
     */
    public function scanChildren(): Traversable
    {
        return $this->scanChildList();
    }

    /**
     * Get all child elements
     *
     * @return array<static>
     */
    public function getChildren(): array
    {
        return iterator_to_array($this->scanChildList());
    }

    /**
     * Get first child element
     */
    public function getFirstChild(): ?static
    {
        return $this->getFirstChildNode();
    }

    /**
     * Get last child element
     */
    public function getLastChild(): ?static
    {
        return $this->getLastChildNode();
    }

    /**
     * Get child element by index
     */
    public function getNthChild(
        int $index
    ): ?static {
        return $this->getNthChildNode($index);
    }

    /**
     * Scan list of children by formula
     *
     * @return Traversable<static>
     */
    public function scanNthChildren(
        string $formula
    ): Traversable {
        return $this->scanNthChildList($formula);
    }

    /**
     * Get list of children by formula
     *
     * @return array<static>
     */
    public function getNthChildren(
        string $formula
    ): array {
        return iterator_to_array($this->scanNthChildList($formula));
    }

    /**
     * Scan all children of type
     *
     * @return Traversable<static>
     */
    public function scanChildrenOfType(
        string $name
    ): Traversable {
        return $this->scanChildList($name);
    }

    /**
     * Get all children of type
     *
     * @return array<static>
     */
    public function getChildrenOfType(
        string $name
    ): array {
        return iterator_to_array($this->scanChildList($name));
    }

    /**
     * Get first child of type
     */
    public function getFirstChildOfType(
        string $name
    ): ?static {
        return $this->getFirstChildNode($name);
    }

    /**
     * Get last child of type
     */
    public function getLastChildOfType(
        string $name
    ): ?static {
        return $this->getLastChildNode($name);
    }

    /**
     * Get child of type by index
     */
    public function getNthChildOfType(
        string $name,
        int $index
    ): ?static {
        return $this->getNthChildNode($index, $name);
    }

    /**
     * Scan child of type by formula
     *
     * @return Traversable<static>
     */
    public function scanNthChildrenOfType(
        string $name,
        string $formula
    ): Traversable {
        return $this->scanNthChildList($formula, $name);
    }

    /**
     * Get child of type by formula
     *
     * @return array<static>
     */
    public function getNthChildrenOfType(
        string $name,
        string $formula
    ): array {
        return iterator_to_array($this->scanNthChildList($formula, $name));
    }


    /**
     * Shared child fetcher
     *
     * @return Traversable<static>
     */
    protected function scanChildList(
        ?string $name = null
    ): Traversable {
        foreach ($this->element->childNodes as $node) {
            /** @var DOMNode $node */
            if ($node->nodeType == \XML_ELEMENT_NODE) {
                if (
                    $name !== null &&
                    $node->nodeName != $name
                ) {
                    continue;
                }

                yield $this->wrapDomNode($node);
            }
        }
    }

    /**
     * Get first element in list
     */
    protected function getFirstChildNode(
        ?string $name = null
    ): ?static {
        foreach ($this->element->childNodes as $node) {
            /** @var DOMNode $node */
            if ($node->nodeType == \XML_ELEMENT_NODE) {
                if (
                    $name !== null &&
                    $node->nodeName != $name
                ) {
                    continue;
                }

                return $this->wrapDomNode($node);
            }
        }

        return null;
    }

    /**
     * Get last element in list
     */
    protected function getLastChildNode(
        ?string $name = null
    ): ?static {
        $lastElement = null;

        foreach ($this->element->childNodes as $node) {
            /** @var DOMNode $node */
            if ($node->nodeType == \XML_ELEMENT_NODE) {
                if (
                    $name !== null &&
                    $node->nodeName != $name
                ) {
                    continue;
                }

                $lastElement = $node;
            }
        }

        if ($lastElement !== null) {
            return $this->wrapDomNode($lastElement);
        } else {
            return null;
        }
    }

    /**
     * Get child at index
     */
    protected function getNthChildNode(
        int $index,
        ?string $name = null
    ): ?static {
        if ($index < 1) {
            throw Exceptional::InvalidArgument(
                $index . ' is an invalid child index'
            );
        }

        foreach ($this->element->childNodes as $node) {
            /** @var DOMNode $node */
            if ($node->nodeType == \XML_ELEMENT_NODE) {
                if (
                    $name !== null &&
                    $node->nodeName != $name
                ) {
                    continue;
                }

                $index--;

                if ($index == 0) {
                    return $this->wrapDomNode($node);
                }
            }
        }

        return null;
    }

    /**
     * Get children by formula
     *
     * @return Traversable<static>
     */
    protected function scanNthChildList(
        string $formula,
        string $name = null
    ): Traversable {
        if (is_numeric($formula)) {
            if ($output = $this->getNthChildNode((int)$formula, $name)) {
                yield $output;
                return;
            }
        }

        $formula = strtolower($formula);

        if ($formula == 'even') {
            $formula = '2n';
        } elseif ($formula == 'odd') {
            $formula = '2n+1';
        }

        if (!preg_match('/^([\-]?)([0-9]*)[n]([+]([0-9]+))?$/i', str_replace(' ', '', $formula), $matches)) {
            throw Exceptional::InvalidArgument(
                $formula . ' is not a valid nth-child formula'
            );
        }

        $mod = (int)$matches[2];
        $offset = (int)($matches[4] ?? 0);

        if ($matches[1] == '-') {
            $mod *= -1;
        }

        $i = 0;

        foreach ($this->element->childNodes as $node) {
            /** @var DOMNode $node */
            if ($node->nodeType == \XML_ELEMENT_NODE) {
                if (
                    $name !== null &&
                    $node->nodeName != $name
                ) {
                    continue;
                }

                $i++;

                if ($i % $mod == $offset) {
                    yield $this->wrapDomNode($node);
                }
            }
        }
    }



    /**
     * Wrap DOMNode as Element
     */
    protected static function wrapDomNode(
        DOMNode $node
    ): static {
        if (!$node instanceof DOMElement) {
            throw Exceptional::UnexpectedValue('Node is not an element', null, $node);
        }

        return new static($node);
    }

    /**
     * Wrap DOMNode as Element or null
     */
    protected static function wrapNullableDomNode(
        ?DOMNode $node
    ): ?static {
        if (!$node instanceof DOMElement) {
            return null;
        }

        return new static($node);
    }



    /**
     * Get text content of first child of type
     */
    public function getChildTextContent(
        string $name
    ): ?string {
        if (!$node = $this->getFirstChildOfType($name)) {
            return null;
        }

        return $node->getTextContent();
    }

    /**
     * Get CDATA content of first child of type
     */
    public function getChildCDataContent(
        string $name
    ): ?string {
        if (!$node = $this->getFirstChildOfType($name)) {
            return null;
        }

        return $node->getFirstCDataSection();
    }



    /**
     * Add child to end of node
     */
    public function prependChild(
        Element|string $newChild,
        ?string $value = null
    ): static {
        $node = $this->normalizeInputChild($newChild, $value);

        if ($this->element->firstChild !== null) {
            $node = $this->element->insertBefore($node, $this->element->firstChild);
        } else {
            $node = $this->element->appendChild($node);
        }

        return $this->wrapDomNode($node);
    }

    /**
     * Add child to start of node
     */
    public function appendChild(
        Element|string $newChild,
        ?string $value = null
    ): static {
        $node = $this->normalizeInputChild($newChild, $value);
        $node = $this->element->appendChild($node);

        return $this->wrapDomNode($node);
    }

    /**
     * Replace child node in place
     */
    public function replaceChild(
        Element $origChild,
        Element|string $newChild,
        ?string $value = null
    ): static {
        $origChild = $origChild->getDomElement();
        $node = $this->normalizeInputChild($newChild, $value);
        $this->element->replaceChild($node, $origChild);

        return $this->wrapDomNode($node);
    }

    /**
     * Add child at index
     */
    public function putChild(
        int $index,
        Element|string $newChild,
        ?string $value = null
    ): static {
        $newNode = $this->normalizeInputChild($newChild, $value);
        $origIndex = $index;
        $count = $this->count();
        $i = 0;

        if ($index < 0) {
            $index += $count;
        }

        if ($index < 0) {
            throw Exceptional::OutOfBounds(
                'Index ' . $origIndex . ' is out of bounds'
            );
        }

        if ($index === 0) {
            if ($this->element->firstChild !== null) {
                $newNode = $this->element->insertBefore($newNode, $this->element->firstChild);
            } else {
                $newNode = $this->element->appendChild($newNode);
            }
        } elseif ($index >= $count) {
            $newNode = $this->element->appendChild($newNode);
        } else {
            foreach ($this->element->childNodes as $node) {
                /** @var DOMNode $node */
                if (!$node->nodeType == \XML_ELEMENT_NODE) {
                    continue;
                }

                if ($i >= $index + 1) {
                    $newNode = $this->element->insertBefore($newNode, $node);
                    break;
                }

                $i++;
            }
        }

        return $this->wrapDomNode($newNode);
    }

    /**
     * Add child node before chosen node
     */
    public function insertChildBefore(
        Element $origChild,
        Element|string $newChild,
        ?string $value = null
    ): static {
        $origChild = $origChild->getDomElement();
        $node = $this->normalizeInputChild($newChild, $value);
        $node = $this->element->insertBefore($node, $origChild);

        return $this->wrapDomNode($node);
    }

    /**
     * Add child node after chosen node
     */
    public function insertChildAfter(
        Element $origChild,
        Element|string $newChild,
        ?string $value = null
    ): static {
        $origChild = $origChild->getDomElement();

        if (!$origChild instanceof DOMElement) {
            throw Exceptional::InvalidArgument(
                'Original child is not a valid element'
            );
        }

        do {
            $origChild = $origChild->nextSibling;
        } while (
            $origChild !== null &&
            $origChild->nodeType !== \XML_ELEMENT_NODE
        );

        $node = $this->normalizeInputChild($newChild, $value);

        if ($origChild === null) {
            $node = $this->element->appendChild($node);
        } else {
            $node = $this->element->insertBefore($node, $origChild);
        }

        return $this->wrapDomNode($node);
    }

    /**
     * Remove child node
     *
     * @return $this
     */
    public function removeChild(
        Element $child
    ): static {
        $child = $child->getDomElement();
        $this->element->removeChild($child);
        return $this;
    }

    /**
     * Clear all children from node
     *
     * @return $this
     */
    public function removeAllChildren(): static
    {
        $queue = [];

        foreach ($this->element->childNodes as $node) {
            /** @var DOMNode $node */
            $queue[] = $node;
        }

        foreach ($queue as $node) {
            $this->element->removeChild($node);
        }

        return $this;
    }


    /**
     * Get parent node
     */
    public function getParent(): ?static
    {
        return $this->wrapNullableDomNode($this->element->parentNode);
    }

    /**
     * How many other nodes are in parent
     */
    public function countSiblings(): int
    {
        if (!$this->element->parentNode) {
            return 0;
        }

        $output = -1;

        foreach ($this->element->parentNode->childNodes as $node) {
            /** @var DOMNode $node */
            if ($node->nodeType == \XML_ELEMENT_NODE) {
                $output++;
            }
        }


        if ($output < 0) {
            $output = 0;
        }

        return $output;
    }

    /**
     * Are there any other nodes in parent?
     */
    public function hasSiblings(): bool
    {
        if (!$this->element->parentNode) {
            return true;
        }

        if (!$this->element->previousSibling && !$this->element->nextSibling) {
            return true;
        }

        foreach ($this->element->parentNode->childNodes as $node) {
            /** @var DOMNode $node */
            if ($node === $this->element) {
                continue;
            }

            if ($node->nodeType == \XML_ELEMENT_NODE) {
                return false;
            }
        }

        return true;
    }


    /**
     * Get previous node
     */
    public function getPreviousSibling(): ?static
    {
        $node = $this->element->previousSibling;

        while ($node && $node->nodeType != \XML_ELEMENT_NODE) {
            if (!$node = $node->previousSibling) {
                return null;
            }
        }

        return $this->wrapNullableDomNode($node);
    }

    /**
     * Get next node
     */
    public function getNextSibling(): ?static
    {
        $node = $this->element->nextSibling;

        while ($node && $node->nodeType != \XML_ELEMENT_NODE) {
            if (!$node = $node->nextSibling) {
                return null;
            }
        }

        return $this->wrapNullableDomNode($node);
    }


    /**
     * Insert sibling before this node
     */
    public function insertBefore(
        Element|string $sibling,
        ?string $value = null
    ): static {
        $node = $this->normalizeInputChild($sibling, $value);
        $node = $this->getParentDomElement()->insertBefore($node, $this->element);

        return $this->wrapDomNode($node);
    }

    /**
     * Insert sibling after this node
     */
    public function insertAfter(
        Element|string $sibling,
        ?string $value = null
    ): static {
        $node = $this->normalizeInputChild($sibling, $value);

        $target = $this->element;

        do {
            $target = $target->nextSibling;
        } while ($target && $target->nodeType != \XML_ELEMENT_NODE);

        if (!$target) {
            $node = $this->getParentDomElement()->appendChild($node);
        } else {
            $node = $this->getParentDomElement()->insertBefore($node, $target);
        }

        return $this->wrapDomNode($node);
    }

    /**
     * Replace this node with another
     *
     * @return $this
     */
    public function replaceWith(
        Element|string $sibling,
        ?string $value = null
    ): static {
        $node = $this->normalizeInputChild($sibling, $value);
        $this->getParentDomElement()->replaceChild($node, $this->element);
        $this->element = $node;

        return $this;
    }




    /**
     * Get last comment before this node
     */
    public function getPrecedingComment(): ?string
    {
        if (
            $this->element->previousSibling &&
            $this->element->previousSibling->nodeType == \XML_COMMENT_NODE
        ) {
            return $this->exportComment($this->element->previousSibling);
        }

        return null;
    }

    /**
     * Scan all comments in node
     *
     * @return Traversable<string>
     */
    public function scanAllComments(): Traversable
    {
        foreach ($this->element->childNodes as $node) {
            /** @var DOMNode $node */
            if ($node->nodeType == \XML_COMMENT_NODE) {
                yield $this->exportComment($node);
            }
        }
    }

    /**
     * Get all comments in node
     *
     * @return array<string>
     */
    public function getAllComments(): array
    {
        return iterator_to_array($this->scanAllComments());
    }


    /**
     * Export comment content
     */
    protected function exportComment(
        DOMNode $node
    ): string {
        if (!$node instanceof DOMComment) {
            return '';
        }

        return trim($node->data);
    }




    /**
     * Get element by id
     */
    public function getById(
        string $id
    ): ?static {
        return $this->firstXPath('//*[@id=\'' . $id . '\']');
    }

    /**
     * Scan all nodes of type
     *
     * @return Traversable<static>
     */
    public function scanByType(
        string $type
    ): Traversable {
        foreach ($this->getDomDocument()->getElementsByTagName($type) as $node) {
            /** @var DOMNode $node */
            yield $this->wrapDomNode($node);
        }
    }

    /**
     * Get all nodes of type
     *
     * @return array<static>
     */
    public function getByType(
        string $type
    ): array {
        return iterator_to_array($this->scanByType($type));
    }

    /**
     * Scan all nodes by attribute
     *
     * @return Traversable<static>
     */
    public function scanByAttribute(
        string $name,
        ?string $value = null
    ): Traversable {
        if ($value === null) {
            $path = '//*[@' . $name . ']';
        } else {
            $path = '//*[@' . $name . '=\'' . $value . '\']';
        }

        return $this->scanXPath($path);
    }

    /**
     * Get all nodes by attribute
     *
     * @return array<static>
     */
    public function getByAttribute(
        string $name,
        ?string $value = null
    ): array {
        return iterator_to_array($this->scanByAttribute($name, $value));
    }


    /**
     * Scan nodes matching xPath
     *
     * @return Traversable<static>
     */
    public function scanXPath(
        string $path
    ): Traversable {
        $xpath = new DOMXPath($this->getDomDocument());

        if (!$result = $xpath->query($path, $this->element)) {
            return;
        }

        foreach ($result as $node) {
            /** @var DOMNode $node */
            yield $this->wrapDomNode($node);
        }
    }

    /**
     * Get nodes matching xPath
     *
     * @return array<static>
     */
    public function getXPath(
        string $path
    ): array {
        return iterator_to_array($this->scanXPath($path));
    }

    /**
     * Get first xPath result
     */
    public function firstXPath(
        string $path
    ): ?static {
        $xpath = new DOMXPath($this->getDomDocument());

        if (!$result = $xpath->query($path, $this->element)) {
            return null;
        }

        return $this->wrapNullableDomNode($result->item(0));
    }


    /**
     * Set XML document version
     *
     * @return $this
     */
    public function setXmlVersion(
        string $version
    ): static {
        $this->getDomDocument()->xmlVersion = $version;
        return $this;
    }

    /**
     * Get XML document version
     */
    public function getXmlVersion(): string
    {
        return $this->getDomDocument()->xmlVersion ?? '1.0';
    }

    /**
     * Set XML document encoding
     *
     * @return $this
     */
    public function setDocumentEncoding(
        string $encoding
    ): static {
        $this->getDomDocument()->xmlEncoding = $encoding;
        return $this;
    }

    /**
     * Get XML document encoding
     */
    public function getDocumentEncoding(): string
    {
        return $this->getDomDocument()->xmlEncoding ?? 'UTF-8';
    }

    /**
     * Set document as standalone
     *
     * @return $this
     */
    public function setDocumentStandalone(
        bool $flag
    ): static {
        $this->getDomDocument()->xmlStandalone = $flag;
        return $this;
    }

    /**
     * Is document standalone?
     */
    public function isDocumentStandalone(): bool
    {
        return (bool)$this->getDomDocument()->xmlStandalone;
    }

    /**
     * Normalize XML document
     *
     * @return $this
     */
    public function normalizeDocument(): static
    {
        $this->getDomDocument()->normalizeDocument();
        return $this;
    }



    /**
     * Get root document
     */
    public function getDomDocument(): DOMDocument
    {
        return static::extractOwnerDocument($this->element);
    }

    /**
     * Get inner dom element
     */
    public function getDomElement(): DOMElement
    {
        return $this->element;
    }

    /**
     * Get parent dom element
     */
    public function getParentDomElement(): DOMElement
    {
        if ($this->element->parentNode === null) {
            throw Exceptional::UnexpectedValue('Element has no parent node', null, $this->element);
        }

        if (!$this->element->parentNode instanceof DOMElement) {
            throw Exceptional::UnexpectedValue('Element\'s parent is not an element', null, $this->element);
        }

        return $this->element->parentNode;
    }

    /**
     * Ensure input is DomElement
     */
    protected function normalizeInputChild(
        Element|string $child,
        ?string $value = null
    ): DOMElement {
        $node = null;

        if ($child instanceof Element) {
            $node = $child->getDOMElement();
        } else {
            $child = (string)$child;
        }

        if ($node instanceof DOMElement) {
            $node = $this->getDomDocument()->importNode($node, true);
        } else {
            $node = $this->getDomDocument()->createElement($child, (string)$value);
        }

        if (!$node instanceof DOMElement) {
            throw Exceptional::UnexpectedValue('Node is not an element', null, $node);
        }

        return $node;
    }


    /**
     * Convert to string
     */
    public function __toString(): string
    {
        $output = $this->getDomDocument()->saveXML($this->element);

        if ($output === false) {
            $output = '';
        }

        return $output;
    }

    /**
     * Export document as string
     */
    public function documentToString(): string
    {
        $output = $this->getDomDocument()->saveXML();

        if ($output === false) {
            $output = '';
        }

        return $output;
    }


    /**
     * Export to string
     */
    public function toXmlString(
        bool $embedded = false
    ): string {
        $isRoot = $this->element === $this->getDomDocument()->documentElement;

        if ($isRoot && !$embedded) {
            return $this->documentToString();
        } else {
            return $this->__toString();
        }
    }

    /**
     * Export xml to file
     */
    public function toXmlFile(
        string $path
    ): File {
        if (!class_exists(Atlas::class)) {
            throw Exceptional::ComponentUnavailable(
                'Saving XML to file requires DecodeLabs Atlas'
            );
        }

        $dir = dirname($path);
        Atlas::createDir($dir);

        $this->getDomDocument()->save($path);
        return Atlas::file($path);
    }

    /**
     * Passthrough
     */
    public function toXmlElement(): Element
    {
        return $this;
    }


    /**
     * Normalize string for writing
     */
    protected static function normalizeString(
        string $string
    ): string {
        return (string)preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $string);
    }

    /**
     * Shortcut to set attribute
     *
     * @param string $key
     */
    public function offsetSet(
        mixed $key,
        mixed $value
    ): void {
        $this->setAttribute($key, $value);
    }

    /**
     * Shortcut to get attribute
     *
     * @param string $key
     * @return ?string
     */
    public function offsetGet(mixed $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Shortcut to test for attribute
     *
     * @param string $key
     */
    public function offsetExists(mixed $key): bool
    {
        return $this->hasAttribute($key);
    }

    /**
     * Shortcut to remove attribute
     *
     * @param string $key
     */
    public function offsetUnset(
        mixed $key
    ): void {
        $this->removeAttribute($key);
    }


    /**
     * Dump inner xml
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
    }
}
