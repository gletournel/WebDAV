<?php
/**
 * This file is part of the WebDav package.
 *
 * (c) Geoffroy Letournel <geoffroy.letournel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Grale\WebDav;

use Grale\WebDav\Property\AbstractProperty;

/**
 *
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
class Property extends AbstractProperty
{
    /**
     * @var mixed
     */
    protected $value;

    /**
     * @param string|array $name
     * @param mixed        $value
     */
    public function __construct($name, $value = null)
    {
        $this->setName($name);
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return self
     */
    public static function fromXml(\DOMElement $element, array $xmlNamespaces = array())
    {
        if (isset($xmlNamespaces[$element->namespaceURI])) {
            $prefix = $xmlNamespaces[$element->namespaceURI];
        } else {
            $prefix = $element->prefix;
        }

        return new static(array($prefix, $element->localName), $element->textContent);
    }
}
