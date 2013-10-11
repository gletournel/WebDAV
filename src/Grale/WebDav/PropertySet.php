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
class PropertySet implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * @var array
     */
    protected $properties = array();

    /**
     * @param array $properties
     */
    public function __construct(array $properties = array())
    {
        foreach ($properties as $property) {
            if (is_string($property)) {
                $property = new Property($property);
            }
            $this->add($property);
        }
    }

    /**
     * @param PropertyInterface $property
     * @return self
     */
    public function add(PropertyInterface $property)
    {
        $this->properties[$property->getName()] = $property;
        return $this;
    }

    /**
     * @param string $name
     *
     * @return PropertyInterface
     */
    public function get($name)
    {
        return isset($this->properties[$name]) ? $this->properties[$name] : null;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        return isset($this->properties[$name]);
    }

    /**
     * @param string $name
     */
    public function remove($name)
    {
        unset($this->properties[$name]);
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return array_values($this->properties);
    }

    /**
     * @return array
     */
    public function getNames()
    {
        return array_keys($this->properties);
    }

    /**
     * @return Iterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->properties);
    }

    /**
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @param string $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->has($offset) ? $this->get($offset)->getValue() : null;
    }

    /**
     * @param string $offset
     * @param mixed  $value
     */
    public function offsetSet($offset, $value)
    {
        $this->add(new Property($offset, $value));
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->properties);
    }
}
