# Exemplar — Package Specification

> **Cluster:** `data`
> **Language:** `php`
> **Milestone:** `m5`
> **Repo:** `https://github.com/decodelabs/exemplar`
> **Role:** XML reader / writer

This document describes the purpose, contracts, and design of **Exemplar** within the Decode Labs ecosystem.

It is aimed at:

- Developers **using** Exemplar in their own applications or libraries.
- Contributors **maintaining or extending** Exemplar.
- Tools and AI assistants that need to reason about its behaviour.

---

## 1. Overview

### 1.1 Purpose

Exemplar provides a set of exhaustive and intuitive interfaces for reading, writing, and manipulating XML documents and fragments. It wraps PHP's DOM and XMLWriter functionality with a more convenient and expressive API, supporting both reading/manipulating existing XML documents and programmatically generating new XML output. Exemplar simplifies common XML operations like attribute management, child element traversal, XPath queries, CDATA handling, and document serialization.

### 1.2 Non-Goals

Exemplar does **not**:

- Provide XML schema validation or DTD validation beyond basic DTD writing support
- Implement XSLT transformation capabilities
- Provide XML parsing performance optimizations beyond what PHP's DOM provides
- Support XML namespaces beyond basic namespace prefix handling
- Provide XML canonicalization or signature capabilities
- Implement XML streaming or SAX-style parsing
- Provide XML-to-object mapping or ORM-like functionality beyond basic serialization interfaces
- Handle XML security concerns like XXE attacks (consumers must handle these separately)

Exemplar focuses on providing a convenient, fluent API for XML manipulation and generation, not on advanced XML processing features.

---

## 2. Role in the Ecosystem

### 2.1 Cluster & Positioning

- **Cluster:** `data` (see Chorus taxonomy)
- Exemplar is positioned as a data manipulation library for XML documents. It provides foundational XML reading and writing capabilities that can be used by higher-level packages for data serialization, configuration management, content generation, and document processing. It sits alongside other data manipulation packages like Collections and Mesa (CSV handling).

### 2.2 Typical Usage Contexts

Typical places Exemplar appears:

- Configuration file reading and writing (XML-based configs)
- Data serialization and deserialization
- Content generation (RSS feeds, sitemaps, etc.)
- Document processing and transformation
- Integration with XML-based APIs
- Template rendering that outputs XML
- Data export/import functionality
- Content management systems that work with XML

Exemplar is intended to be used whenever a Decode Labs package needs to work with XML documents in a convenient, type-safe manner.

---

## 3. Public Surface

> This section focuses on the conceptual API, not every symbol.

### 3.1 Key Types

The primary public types are:

- `DecodeLabs\Exemplar\Element`
  Main class for reading and manipulating XML documents. Wraps `DOMElement` and provides a fluent API for attribute management, child element traversal, XPath queries, content manipulation, and document serialization. Implements `Markup`, `Consumer`, `Provider`, `AttributeContainer`, `Countable`, `ArrayAccess`, and `Dumpable`.

- `DecodeLabs\Exemplar\Writer`
  Class for programmatically generating XML output. Wraps `XMLWriter` and provides a fluent API with CSS selector-like syntax for element creation, attribute setting, CDATA handling, comments, processing instructions, and document serialization. Implements `Markup`, `Provider`, `AttributeContainer`, `ArrayAccess`, and `Dumpable`.

- `DecodeLabs\Exemplar\Consumer`
  Interface for types that can be created from XML. Defines static factory methods: `fromXml()`, `fromXmlFile()`, `fromXmlString()`, `fromXmlElement()`.

- `DecodeLabs\Exemplar\Provider`
  Interface for types that can be converted to XML. Defines methods: `toXmlString()`, `toXmlFile()`, `toXmlElement()`.

- `DecodeLabs\Exemplar\Serializable`
  Interface extending both `Consumer` and `Provider`, adding `xmlUnserialize()` and `xmlSerialize()` methods for bidirectional XML serialization.

- `DecodeLabs\Exemplar\SerializableTrait`
  Trait providing default implementation for the `Serializable` interface, including factory methods and serialization helpers.

- `DecodeLabs\Exemplar\WriterNode`
  Internal enum representing the current state of the XML writer (Element, CData, CDataElement, Comment, PI).

- `DecodeLabs\PHPStan\ExemplarReflectionExtension`
  PHPStan extension for static analysis of dynamic property access on `Element` instances (for child element access via `__get()`).

### 3.2 Main Entry Points

**Reading XML:**

```php
use DecodeLabs\Exemplar\Element as XmlElement;

// From file
$element = XmlElement::fromFile('/path/to/file.xml');

// From string
$element = XmlElement::fromString($xmlString);

// From DOMDocument or DOMElement
$element = XmlElement::fromDomDocument($document);
$element = XmlElement::fromDomElement($domElement);

// From any XML type (auto-detection)
$element = XmlElement::fromXml($anyXmlSource);
```

**Writing XML:**

```php
use DecodeLabs\Exemplar\Writer as XmlWriter;

// Create writer
$writer = new XmlWriter();
$writer->writeHeader();

// Write elements with CSS selector-like syntax
$writer->{'ns:section[ns:attr1=value].test'}(function ($writer) {
    $writer->{'title#main'}('This is a title');
    $writer->{'@body'}('This is CDATA content');
});

echo $writer;
```

---

## 4. Dependencies

### 4.1 Decode Labs

- `decodelabs/coercion` (required)
  Used for type coercion when setting attributes and converting values to strings.

- `decodelabs/collections` (required)
  Used for `AttributeContainer` interface and trait for attribute management.

- `decodelabs/elementary` (required)
  Used for `Markup` interface for stringable markup objects.

- `decodelabs/exceptional` (required)
  Used for exception handling throughout the library.

- `decodelabs/nuance` (required)
  Used for `Dumpable` interface and `NuanceEntity` for debugging and inspection.

### 4.2 External

- PHP DOM extension (required)
  Core PHP extension providing `DOMDocument`, `DOMElement`, `DOMNode`, `DOMXPath`, etc.

- PHP XMLWriter extension (required)
  Core PHP extension providing `XMLWriter` for generating XML output.

### 4.3 Optional Integrations

- `decodelabs/atlas` — Detected at runtime if installed, used for file operations when saving XML to files via `Element::toXmlFile()` and `Writer::toXmlFile()`. Required for file-based operations.

---

## 5. Behaviour & Contracts

### 5.1 Invariants

- `Element` instances always wrap a valid `DOMElement` that belongs to a `DOMDocument`
- `Element` instances maintain references to the underlying DOM structure; modifications are reflected in the DOM
- `Writer` instances maintain state about the current writing context (element, CDATA, comment, PI)
- `Writer` instances must have a header written before root elements can be written
- `Writer` instances must finalize before output can be retrieved
- All XML output is normalized to remove invalid XML characters
- Attribute values are coerced to strings using `Coercion::toString()`
- Child element access via `__get()` returns arrays of `Element` instances
- XPath queries operate on the document containing the element
- HTML files are automatically detected and loaded using `loadHTMLFile()` / `loadHTML()`
- XML strings without XML declarations are automatically prepended with a default declaration
- Document formatting is enabled by default (`formatOutput = true`)

### 5.2 Input & Output Contracts

**Element Factory Methods:**
- `fromXml(mixed $xml): static` — Accepts `Element`, `Provider`, `DOMDocument`, `DOMElement`, `File` (if Atlas available), string, or Stringable. Returns `Element` instance.
- `fromFile(string $path): static` — Loads XML or HTML from file path. Auto-detects HTML by extension (.html, .htm).
- `fromString(string $xml): static` — Loads XML or HTML from string. Auto-detects HTML by DOCTYPE.
- `fromXmlFile(string $path): static` — Loads XML from file path.
- `fromXmlString(string $xml): static` — Loads XML from string, prepending XML declaration if missing.
- `fromHtmlFile(string $path): static` — Loads HTML from file path.
- `fromHtmlString(string $xml): static` — Loads HTML from string.
- `fromDomDocument(DOMDocument $document): static` — Wraps document element from DOMDocument.
- `fromDomElement(DOMElement $element): static` — Wraps DOMElement.

**Element Attribute Methods:**
- `setAttribute(string $key, mixed $value): static` — Sets attribute, coercing value to string.
- `getAttribute(string $key): ?string` — Gets attribute value or null if not present.
- `getBooleanAttribute(string $name): bool` — Converts attribute to boolean (handles 'true', 'false', '1', '0', 'yes', 'no', 'on', 'off', 'enabled', 'disabled').
- `hasAttribute(string ...$keys): bool` — Checks if any of the specified attributes exist.
- `hasAttributes(string ...$keys): bool` — Checks if all of the specified attributes exist.
- `removeAttribute(string ...$keys): static` — Removes specified attributes.
- `clearAttributes(): static` — Removes all attributes.
- `setAttributes(iterable $attributes, mixed ...$attributeList): static` — Sets multiple attributes.
- `replaceAttributes(iterable $attributes, mixed ...$attributeList): static` — Replaces all attributes.

**Element Content Methods:**
- `setTextContent(string $content): static` — Sets element text content.
- `getTextContent(): string` — Gets all text content in node.
- `getComposedTextContent(): string` — Gets normalized text content (handles whitespace and CDATA).
- `setInnerXml(string $inner): static` — Sets inner XML string.
- `getInnerXml(): string` — Gets inner XML string.
- `getComposedInnerXml(): string` — Gets normalized inner XML.
- `setCDataContent(string $content): static` — Sets CDATA content.
- `getFirstCDataSection(): ?string` — Gets first CDATA section.
- `getAllCDataSections(): array<string>` — Gets all CDATA sections.

**Element Child Methods:**
- `getChildren(): array<Element>` — Gets all child elements.
- `getFirstChild(): ?Element` — Gets first child element.
- `getLastChild(): ?Element` — Gets last child element.
- `getNthChild(int $index): ?Element` — Gets child at index (1-based).
- `getChildrenOfType(string $name): array<Element>` — Gets all children of specified type.
- `getFirstChildOfType(string $name): ?Element` — Gets first child of specified type.
- `getLastChildOfType(string $name): ?Element` — Gets last child of specified type.
- `getNthChildOfType(string $name, int $index): ?Element` — Gets nth child of specified type.
- `scanChildren(): Traversable<Element>` — Iterates over all child elements.
- `scanChildrenOfType(string $name): Traversable<Element>` — Iterates over children of specified type.
- `scanNthChildren(string $formula): Traversable<Element>` — Iterates over children matching nth-child formula (e.g. '2n', '2n+1', 'even', 'odd').
- `count(): int` — Counts child elements.
- `countType(string $name): int` — Counts children of specified type.
- `hasChildren(): bool` — Checks if element has any child elements.
- `appendChild(Element|string $newChild, ?string $value = null): Element` — Appends child element.
- `prependChild(Element|string $newChild, ?string $value = null): Element` — Prepends child element.
- `insertChildBefore(Element $origChild, Element|string $newChild, ?string $value = null): Element` — Inserts child before specified element.
- `insertChildAfter(Element $origChild, Element|string $newChild, ?string $value = null): Element` — Inserts child after specified element.
- `replaceChild(Element $origChild, Element|string $newChild, ?string $value = null): Element` — Replaces child element.
- `putChild(int $index, Element|string $newChild, ?string $value = null): Element` — Inserts child at index.
- `removeChild(Element $child): static` — Removes child element.
- `removeAllChildren(): static` — Removes all child elements.

**Element Query Methods:**
- `getById(string $id): ?Element` — Gets element by ID attribute.
- `scanByType(string $type): Traversable<Element>` — Iterates over all elements of specified type in document.
- `getByType(string $type): array<Element>` — Gets all elements of specified type in document.
- `scanByAttribute(string $name, ?string $value = null): Traversable<Element>` — Iterates over elements with specified attribute.
- `getByAttribute(string $name, ?string $value = null): array<Element>` — Gets elements with specified attribute.
- `scanXPath(string $path): Traversable<Element>` — Iterates over elements matching XPath query.
- `getXPath(string $path): array<Element>` — Gets elements matching XPath query.
- `firstXPath(string $path): ?Element` — Gets first element matching XPath query.

**Element Serialization Methods:**
- `__toString(): string` — Converts element to XML string.
- `documentToString(): string` — Converts entire document to XML string.
- `toXmlString(bool $embedded = false): string` — Converts to XML string (with or without document declaration).
- `toXmlFile(string $path): File` — Saves to file (requires Atlas).
- `toXmlElement(): Element` — Returns self (passthrough).

**Writer Methods:**
- `create(): static` — Creates writer in memory.
- `createFile(string $path): static` — Creates writer writing to file.
- `writeHeader(string $version = '1.0', string $encoding = 'UTF-8', bool $standalone = false): static` — Writes XML declaration.
- `writeDtd(string $name, ?string $publicId = null, ?string $systemId = null, ?string $subset = null): static` — Writes DTD declaration.
- `writeDtdAttlist(string $name, string $content): static` — Writes DTD attribute list.
- `writeDtdElement(string $name, string $content): static` — Writes DTD element declaration.
- `writeDtdEntity(string $name, string $content, bool $isParam, string $publicId, string $systemId, string $nDataId): static` — Writes DTD entity declaration.
- `writeElement(string $name, mixed $content = null, ?array $attributes = null): static` — Writes complete element.
- `startElement(string $name, ?array $attributes = null): static` — Starts element (with CSS selector-like syntax support).
- `endElement(): static` — Ends current element.
- `setElementContent(mixed $content): static` — Sets element content (supports callables, iterables, markup).
- `writeCData(?string $content): static` — Writes complete CDATA section.
- `writeCDataElement(string $name, ?string $content, ?array $attributes = null): static` — Writes element with CDATA content.
- `startCData(): static` — Starts CDATA section.
- `writeCDataContent(?string $content): static` — Writes CDATA content.
- `endCData(): static` — Ends CDATA section.
- `writeComment(?string $comment): static` — Writes complete comment.
- `startComment(): static` — Starts comment.
- `writeCommentContent(?string $comment): static` — Writes comment content.
- `endComment(): static` — Ends comment.
- `writePi(string $target, ?string $content): static` — Writes complete processing instruction.
- `startPi(string $target): static` — Starts processing instruction.
- `writePiContent(?string $content): static` — Writes PI content.
- `endPi(): static` — Ends processing instruction.
- `writeRaw(?string $content): static` — Writes raw XML.
- `importXmlElement(Element $element): static` — Imports XML element into writer.
- `finalize(): static` — Finalizes document and flushes output.
- `toXmlString(bool $embedded = false): string` — Converts to XML string.
- `toXmlFile(string $path): File` — Saves to file (requires Atlas).
- `toXmlElement(): Element` — Converts to Element instance.

**Writer CSS Selector Syntax:**
- `tagName` — Element name
- `tagName.class1.class2` — Element with classes (sets `class` attribute)
- `tagName#id` — Element with ID (sets `id` attribute)
- `tagName[attr=value]` — Element with attribute (sets attribute)
- `@tagName` — Element with CDATA content
- `ns:tagName` — Namespaced element
- Combinations: `ns:tagName[attr=value].class#id`

**Serializable Interface:**
- `xmlUnserialize(Element $element): void` — Deserializes object from XML element.
- `xmlSerialize(Writer $writer): void` — Serializes object to XML writer.

---

## 6. Error Handling

- Invalid XML input throws `Exceptional::Io` with previous exception from DOM/XMLWriter
- Missing document element throws `Exceptional::UnexpectedValue`
- Invalid element operations (e.g. ending element when not in element) throw `Exceptional::Logic`
- Invalid attribute definitions in CSS selector syntax throw `Exceptional::UnexpectedValue`
- Invalid nth-child formulas throw `Exceptional::InvalidArgument`
- Out-of-bounds child indices throw `Exceptional::OutOfBounds`
- Missing parent node operations throw `Exceptional::UnexpectedValue`
- File operations without Atlas throw `Exceptional::ComponentUnavailable`
- Invalid XML characters are automatically removed during normalization
- XML parsing errors are wrapped in `Exceptional::Io` exceptions

---

## 7. Configuration & Extensibility

- No runtime configuration surface
- Document formatting is enabled by default (`formatOutput = true`)
- Indentation is set to 4 spaces by default in Writer
- XML version defaults to '1.0'
- Encoding defaults to 'UTF-8'
- Standalone defaults to false
- Custom serialization can be implemented via `Serializable` interface
- Custom element types can extend `Element` or implement `Consumer`/`Provider`
- Writer supports raw attribute names list for unescaped attribute values
- Element supports dynamic property access via `__get()` for child element access
- Writer supports CSS selector-like syntax for element creation
- Writer supports callable content for dynamic element generation

---

## 8. Interactions with Other Packages

### 8.1 Elementary

Exemplar uses Elementary for:
- `Markup` interface for stringable markup objects
- Integration with Elementary-based markup systems

### 8.2 Collections

Exemplar uses Collections for:
- `AttributeContainer` interface and trait for attribute management
- Consistent attribute API across markup libraries

### 8.3 Coercion

Exemplar uses Coercion for:
- Type coercion when setting attributes and converting values to strings
- Safe type conversion for XML content

### 8.4 Exceptional

Exemplar uses Exceptional for:
- Exception handling throughout the library
- Consistent error reporting

### 8.5 Nuance

Exemplar uses Nuance for:
- `Dumpable` interface for debugging and inspection
- `NuanceEntity` for structured debugging output

### 8.6 Atlas

Atlas is optionally used by Exemplar for:
- File operations when saving XML to files
- Directory creation for file output
- File path handling

### 8.7 Other Packages

Exemplar may be used by:
- Configuration packages for XML-based configs
- Content generation packages for RSS feeds, sitemaps, etc.
- Data serialization packages for XML serialization
- Integration packages for XML-based APIs
- Template rendering packages that output XML

---

## 9. Usage Examples

### 9.1 Reading and Manipulating XML

```php
use DecodeLabs\Exemplar\Element as XmlElement;

$element = XmlElement::fromFile('/path/to/file.xml');

if ($element->hasAttribute('old')) {
    $element->removeAttribute('old');
}

$element->setAttribute('new', 'value');

foreach ($element->scanChildrenOfType('section') as $sectTag) {
    $inner = $sectTag->getFirstChildOfType('title');
    $sectTag->removeChild($inner);
    
    // Flatten to plain text
    echo $sectTag->getComposedTextContent();
}

file_put_contents('newfile.xml', (string)$element);
```

### 9.2 Writing XML

```php
use DecodeLabs\Exemplar\Writer as XmlWriter;

$writer = new XmlWriter();
$writer->writeHeader();

$writer->{'ns:section[ns:attr1=value].test'}(function ($writer) {
    $writer->{'title#main'}('This is a title');
    $writer->{'@body'}('This is an element with content wrapped in CDATA tags.');
    $writer->writeCData('This is plain CDATA');
});

echo $writer;
```

### 9.3 XPath Queries

```php
use DecodeLabs\Exemplar\Element as XmlElement;

$element = XmlElement::fromFile('/path/to/file.xml');

// Find all elements with specific attribute
$items = $element->getByAttribute('type', 'item');

// Find element by ID
$item = $element->getById('main-item');

// Complex XPath query
$results = $element->getXPath('//item[@price > 100]/name');
```

### 9.4 Child Element Access

```php
use DecodeLabs\Exemplar\Element as XmlElement;

$element = XmlElement::fromFile('/path/to/file.xml');

// Access children by type via dynamic property
$sections = $element->section; // array of <section> elements

// Get first child of type
$title = $element->getFirstChildOfType('title');

// Iterate over children
foreach ($element->scanChildren() as $child) {
    echo $child->getTagName();
}

// Nth-child formulas
$evenItems = $element->getNthChildren('2n');
$oddItems = $element->getNthChildren('2n+1');
```

### 9.5 Attribute Management

```php
use DecodeLabs\Exemplar\Element as XmlElement;

$element = XmlElement::fromFile('/path/to/file.xml');

// Set attributes
$element->setAttribute('id', 'main');
$element->setAttributes(['class' => 'container', 'data-id' => 123]);

// Get attributes
$id = $element->getAttribute('id');
$isActive = $element->getBooleanAttribute('active');

// ArrayAccess interface
$element['class'] = 'new-class';
$class = $element['class'];
unset($element['old-attr']);
```

### 9.6 CDATA Handling

```php
use DecodeLabs\Exemplar\Element as XmlElement;

$element = XmlElement::fromFile('/path/to/file.xml');

// Set CDATA content
$element->setCDataContent('Raw content with <tags>');

// Get CDATA sections
$cdata = $element->getFirstCDataSection();
$allCdata = $element->getAllCDataSections();
```

### 9.7 Writer with Callable Content

```php
use DecodeLabs\Exemplar\Writer as XmlWriter;

$writer = new XmlWriter();
$writer->writeHeader();

$writer->section(function ($writer) {
    $writer->title('Main Title');
    
    $items = ['Item 1', 'Item 2', 'Item 3'];
    foreach ($items as $item) {
        $writer->item($item);
    }
});

echo $writer;
```

### 9.8 Serializable Objects

```php
use DecodeLabs\Exemplar\Serializable;
use DecodeLabs\Exemplar\SerializableTrait;
use DecodeLabs\Exemplar\Element;
use DecodeLabs\Exemplar\Writer;

class MyObject implements Serializable
{
    use SerializableTrait;
    
    public string $name;
    public int $value;
    
    public function xmlUnserialize(Element $element): void
    {
        $this->name = $element->getAttribute('name') ?? '';
        $this->value = (int)($element->getAttribute('value') ?? 0);
    }
    
    public function xmlSerialize(Writer $writer): void
    {
        $writer->object([
            'name' => $this->name,
            'value' => $this->value
        ]);
    }
}

// Usage
$obj = MyObject::fromXmlFile('/path/to/file.xml');
$xml = $obj->toXmlString();
```

### 9.9 Document Properties

```php
use DecodeLabs\Exemplar\Element as XmlElement;

$element = XmlElement::fromFile('/path/to/file.xml');

// Set document properties
$element->setXmlVersion('1.1');
$element->setDocumentEncoding('ISO-8859-1');
$element->setDocumentStandalone(true);

// Get document properties
$version = $element->getXmlVersion();
$encoding = $element->getDocumentEncoding();
$standalone = $element->isDocumentStandalone();
```

### 9.10 Sibling Navigation

```php
use DecodeLabs\Exemplar\Element as XmlElement;

$element = XmlElement::fromFile('/path/to/file.xml');
$child = $element->getFirstChild();

// Navigate siblings
$previous = $child->getPreviousSibling();
$next = $child->getNextSibling();
$siblingCount = $child->countSiblings();
$hasSiblings = $child->hasSiblings();

// Insert siblings
$child->insertBefore('new-element', 'content');
$child->insertAfter('new-element', 'content');
$child->replaceWith('replacement-element', 'content');
```

---

## 10. Implementation Notes (for Contributors)

### 10.1 Internal Architecture

At a high level, Exemplar:
- Wraps PHP's DOM extension (`DOMDocument`, `DOMElement`, `DOMNode`, `DOMXPath`) for reading and manipulating XML
- Wraps PHP's XMLWriter extension for generating XML output
- Uses `Coercion` for type-safe attribute value conversion
- Uses `AttributeContainer` from Collections for consistent attribute API
- Implements `Markup` from Elementary for stringable markup objects
- Uses `Nuance` for debugging and inspection capabilities
- Maintains references to underlying DOM structures; modifications are reflected immediately
- Normalizes XML strings to remove invalid characters
- Supports both memory-based and file-based XMLWriter instances
- Provides CSS selector-like syntax parsing for Writer element creation
- Implements dynamic property access via `__get()` for child element access

### 10.2 DOM Wrapping

`Element` wraps `DOMElement` and maintains a reference to the underlying DOM structure. All modifications are performed on the DOM directly, ensuring consistency. The `DOMDocument` is accessed via `ownerDocument` and formatting is enabled by default.

### 10.3 Writer State Management

`Writer` maintains state about the current writing context using the `WriterNode` enum. This ensures proper nesting of elements, CDATA sections, comments, and processing instructions. The `completeCurrentNode()` method handles state transitions and content writing.

### 10.4 CSS Selector Syntax Parsing

Writer supports CSS selector-like syntax for element creation:
- Classes are parsed from dot-separated parts and set as `class` attribute
- IDs are parsed from `#` prefix and set as `id` attribute
- Attributes are parsed from `[key=value]` syntax
- Namespace prefixes are preserved
- `@` prefix indicates CDATA content

### 10.5 Dynamic Property Access

`Element` implements `__get()` to provide dynamic access to child elements by type. This is supported by a PHPStan extension that provides proper type information for static analysis.

### 10.6 Nth-Child Formula Parsing

Element supports nth-child formulas for selecting children:
- Numeric values select specific indices
- 'even' and 'odd' are converted to '2n' and '2n+1'
- Formulas like '2n', '2n+1', '-2n+5' are parsed and evaluated
- Formulas are applied during iteration

### 10.7 XML Normalization

XML strings are normalized to remove invalid XML characters using a regex pattern that matches valid XML character ranges. This ensures output is always valid XML.

### 10.8 File Operations

File operations require Atlas to be available. If Atlas is not installed, file operations throw `ComponentUnavailable` exceptions. File operations use Atlas for directory creation and file handling.

### 10.9 Performance Considerations

- DOM operations are performed directly on PHP's DOM structures
- XPath queries create new `DOMXPath` instances for each query
- Child element iteration uses direct DOM traversal
- Writer uses buffered output for memory efficiency
- File-based writers use streaming output
- Element wrapping creates new `Element` instances but maintains DOM references

### 10.10 Gotchas & Historical Decisions

- HTML files are automatically detected and loaded using HTML parsing methods
- XML strings without declarations are automatically prepended with default declaration
- Document formatting is enabled by default for readability
- Attribute values are always coerced to strings
- Boolean attributes use special parsing for common true/false representations
- Child indices are 1-based (not 0-based) to match CSS nth-child semantics
- Dynamic property access returns arrays, not single elements
- Writer state must be properly managed; invalid state transitions throw exceptions
- File operations require Atlas; this is an optional dependency
- XML normalization removes invalid characters but may not handle all edge cases

---

## 11. Testing & Quality

- **Code Quality Score:** 4/5
- **README Quality Score:** 3/5
- **Documentation Score:** 0/5 (this spec)
- **Test Coverage Score:** 0/5

See `composer.json` for supported PHP versions.

---

## 12. Roadmap & Future Ideas

- Additional XML processing features (XSLT, schema validation)
- Performance optimizations for large documents
- Streaming XML parsing support
- Enhanced namespace support
- XML canonicalization support
- XML signature support
- Better error messages for invalid XML
- Support for XML fragments
- Enhanced HTML5 support
- Better integration with Elementary markup system
- Support for XML namespaces in Writer
- Enhanced XPath query builder
- Support for XML entity references
- Better handling of XML processing instructions
- Support for XML comments in Writer element creation
- Enhanced serialization support

---

## 13. References

- [Elementary Package](https://github.com/decodelabs/elementary) — Markup base library
- [Collections Package](https://github.com/decodelabs/collections) — Data structures
- [Coercion Package](https://github.com/decodelabs/coercion) — Type casting
- [Exceptional Package](https://github.com/decodelabs/exceptional) — Enhanced exceptions
- [Nuance Package](https://github.com/decodelabs/nuance) — Type inspection
- [Atlas Package](https://github.com/decodelabs/atlas) — Filesystem operations
- [PHP DOM Documentation](https://www.php.net/manual/en/book.dom.php) — PHP DOM extension
- [PHP XMLWriter Documentation](https://www.php.net/manual/en/book.xmlwriter.php) — PHP XMLWriter extension
- [XPath Documentation](https://www.w3.org/TR/xpath/) — XPath specification
- [XML Specification](https://www.w3.org/XML/) — XML specification

