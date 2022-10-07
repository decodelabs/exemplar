# Exemplar

[![PHP from Packagist](https://img.shields.io/packagist/php-v/decodelabs/exemplar?style=flat)](https://packagist.org/packages/decodelabs/exemplar)
[![Latest Version](https://img.shields.io/packagist/v/decodelabs/exemplar.svg?style=flat)](https://packagist.org/packages/decodelabs/exemplar)
[![Total Downloads](https://img.shields.io/packagist/dt/decodelabs/exemplar.svg?style=flat)](https://packagist.org/packages/decodelabs/exemplar)
[![GitHub Workflow Status](https://img.shields.io/github/workflow/status/decodelabs/exemplar/Integrate)](https://github.com/decodelabs/exemplar/actions/workflows/integrate.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-44CC11.svg?longCache=true&style=flat)](https://github.com/phpstan/phpstan)
[![License](https://img.shields.io/packagist/l/decodelabs/exemplar?style=flat)](https://packagist.org/packages/decodelabs/exemplar)

### Powerful XML tools for PHP.

Exemplar provides a set of exhaustive and intuitive interfaces for reading, writing and manipulating XML documents and fragments.

_Get news and updates on the [DecodeLabs blog](https://blog.decodelabs.com)._

---


## Installation

```bash
composer require decodelabs/exemplar
```

## Usage

### Reading & manipulating

Access and manipulate XML files with a consolidated interface wrapping the DOM functionality available in PHP:

```php
use DecodeLabs\Exemplar\Element as XmlElement;

$element = XmlElement::fromFile('/path/to/my/file.xml');

if($element->hasAttribute('old')) {
    $element->removeAttribute('old');
}

$element->setAttribute('new', 'value');

foreach($element->scanChildrenOfType('section') as $sectTag) {
    $inner = $sectTag->getFirstChildOfType('title');
    $sectTag->removeChild($inner);

    // Flatten to plain text
    echo $sectTag->getComposedTextContent();
}

file_put_contents('newfile.xml', (string)$element);
```

See [Element.php](./src/Element.php) for the full interface.


### Writing

Programatically generate XML output with a full-featured wrapper around PHP's XML Writer:

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

This creates:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<ns:section ns:attr1="value" class="test">
    <title id="main">This is a title</title>
    <body><![CDATA[This is an element with content wrapped in CDATA tags.]]></body>
<![CDATA[This is plain CDATA]]></ns:section>
```

See [Writer.php](./src/Writer.php) for the full interface.


## Licensing
Exemplar is licensed under the MIT License. See [LICENSE](./LICENSE) for the full license text.
