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
     * @return string
     */
    public function getName();

    /**
     * @return mixed
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
