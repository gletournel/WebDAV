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

/**
 *
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
interface PropertyInterface
{
    /**
     * Returns the name of this property.
     *
     * @return string The name of this property
     */
    public function getName();

    /**
     * Returns the value of this property.
     *
     * @return mixed The value of this property
     */
    public function getValue();

    /**
     * @param \DOMElement $element       The property as an XML element
     * @param array       $xmlNamespaces
     *
     * @return self
     */
    public static function fromXml(\DOMElement $element, array $xmlNamespaces = array());
}
