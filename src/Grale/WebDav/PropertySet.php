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
 * A set of WebDAV properties
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
class PropertySet implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * @var array The set of properties
     */
    protected $properties = array();

    /**
     * @param array $properties The set of properties
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
     * Adds a new property to this set.
     *
     * @param PropertyInterface $property The property to add
     * @return self Provides a fluent interface
     */
    public function add(PropertyInterface $property)
    {
        $this->properties[$property->getName()] = $property;
        return $this;
    }

    /**
     * Retrieves the property with the specified name.
     *
     * @param string $name The name of the property to retrieve
     *
     * @return PropertyInterface Returns the desired property or <tt>null</tt>
     * if no property exists for the specified name
     */
    public function get($name)
    {
        return isset($this->properties[$name]) ? $this->properties[$name] : null;
    }

    /**
     * Checks if this set contains the property with the specified name.
     *
     * @param string $name The name of the property
     * @return bool Returns true if this set contains the property, or false otherwise
     */
    public function has($name)
    {
        return isset($this->properties[$name]);
    }

    /**
     * Removes the indicated property from this set.
     *
     * @param string $name The property name to remove
     */
    public function remove($name)
    {
        unset($this->properties[$name]);
    }

    /**
     * Returns all properties present in this set.
     *
     * @return array Returns an array of all properties present in this set
     */
    public function getAll()
    {
        return array_values($this->properties);
    }

    /**
     * Returns the names of all properties present in this set.
     *
     * @return array Returns an array of all property names present in this set
     */
    public function getNames()
    {
        return array_keys($this->properties);
    }

    /**
     * Returns an iterator over all properties in this set.
     *
     * @return \Iterator An iterator over {@link PropertyInterface}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->properties);
    }

    /**
     * Whether or not an offset exists.
     *
     * @param string $offset An offset to check for
     * @return bool Returns true if offset exists or false otherwise
     *
     * @internal
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * Returns the value at specified offset.
     *
     * @param string $offset The offset to retrieve
     * @return mixed Returns the value of the property at specified offset
     *
     * @internal
     */
    public function offsetGet($offset)
    {
        return $this->has($offset) ? $this->get($offset)->getValue() : null;
    }

    /**
     * Assigns a value to the specified offset.
     *
     * @param string $offset The offset to assign the value to
     * @param mixed  $value  The value to set
     *
     * @internal
     */
    public function offsetSet($offset, $value)
    {
        $this->add(new Property($offset, $value));
    }

    /**
     * Unsets an offset.
     *
     * @param string $offset The offset to unset
     *
     * @internal
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * Returns the size of this set.
     *
     * @return int Returns the number of properties present in this set
     */
    public function count()
    {
        return count($this->properties);
    }
}
