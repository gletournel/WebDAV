<?php
/**
 * This file is part of the WebDav package.
 *
 * (c) Geoffroy Letournel <geoffroy.letournel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Grale\WebDav\Property;

use Grale\WebDav\PropertyInterface;

/**
 *
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
abstract class AbstractProperty implements PropertyInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var string
     */
    protected $localName;

    /**
     * @param string|array $name
     */
    protected function setName($name)
    {
        $prefix    = null;
        $localName = null;

        if (is_array($name) && count($name) > 0) {
            $localName = $name[1];
            $prefix    = $name[0];
            $name      = implode(':', $name);
        } elseif (($pos = strpos($name, ':')) !== false) {
            $localName = substr($name, $pos + 1);
            $prefix    = substr($name, 0, $pos);
        } else {
            $localName = $name;
        }

        $this->name = $name;
        $this->prefix = $prefix;
        $this->localName = $localName;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getLocalName()
    {
        return $this->localName;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }
}
