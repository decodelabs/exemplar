<?php

/**
 * Exemplar
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Exemplar;

/**
 * @internal
 */
enum WriterNode
{
    case Element;
    case CData;
    case CDataElement;
    case Comment;
    case PI;
}
