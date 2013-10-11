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
 * Holds a single response describing the effect of a method on resource and/or its properties
 *
 * This class represents a <tt>response</tt> element and is used within a {@link MultiStatus} response
 * as defined in {@link http://www.ietf.org/rfc/rfc2518.txt RFC-2518}.
 *
 * @todo Add the optional additional href tags to comply with the RFC-2518
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
class Response
{
    /**
     * Uses a combination of <tt>href</tt> and <tt>status</tt> elements
     */
    const HREFSTATUS = 1;

    /**
     * Uses <tt>propstat</tt> elements
     */
    const PROPSTATUS = 2;

    /**
     * @var int
     */
    protected $type;

    /**
     * @var string URI of the associated resource
     */
    protected $href;

    /**
     * @var int The HTTP status code that applies to the entire response
     */
    protected $status;

    /**
     * @var array A list of resource properties, grouped by HTTP status code
     */
    protected $properties = array();

    /**
     * @var string An optional response description
     */
    protected $description;

    /**
     * @param string    $href        URI of the associated resource
     * @param int|array $status
     * @param string    $description An optional response description
     *
     * @throws \InvalidArgumentException
     *
     * @todo defines the exception message
     */
    public function __construct($href, $status = null, $description = null)
    {
        $this->href = $href;

        if (is_int($status)) {
            $this->status = $status;
        } elseif (is_array($status)) {
            foreach ($status as $statusCode => $properties) {
                $this->addProperties($properties, $statusCode);
            }
        } elseif ($status !== null) {
            throw new \InvalidArgumentException();
        }

        $this->type = is_int($status) ? self::HREFSTATUS : self::PROPSTATUS;

        if ($description !== null) {
            $this->description = (string)$description;
        }
    }

    /**
     * @return int Returns the response type as an integer
     * @see HREFSTATUS, PROPSTATUS
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string Returns the URI of the associated resource
     */
    public function getHref()
    {
        return $this->href;
    }

    /**
     *
     * This method can be used only with {@link HREFSTATUS} responses
     *
     * @return int Returns the HTTP status code that applies to the entire response
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return bool
     */
    public function hasResource()
    {
    }

    /**
     * @return Resource
     */
    public function getResource()
    {
        // Checking response status == 200

        $resource = new Resource($this->href, $this->getProperties());

        return $resource;
    }

    /**
     * @param int $status
     * @return PropertySet
     */
    public function getProperties($status = 200)
    {
        return isset($this->properties[$status]) ? $this->properties[$status] : null;
    }

    /**
     * @param int $status
     * @return bool
     */
    public function hasProperties($status = 200)
    {
        return isset($this->properties[$status]);
    }

    /**
     * @param int $status
     * @return array
     */
    public function getPropertyNames($status = 200)
    {
        return isset($this->properties[$status]) ? $this->properties[$status]->getNames() : null;
    }

    /**
     * @return string Returns information suitable to be displayed to the user explaining
     * the nature of the response. This description may be NULL.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param array $properties
     * @param int   $status
     */
    public function addProperties(array $properties, $status = 200)
    {
        foreach ($properties as $property) {
            $this->addProperty($property, $status);
        }
    }

    /**
     * @param PropertyInterface $property
     * @param int               $status
     *
     * @return self Provides a fluent interface
     * @todo defines the exceptions
     */
    public function addProperty(PropertyInterface $property, $status = 200)
    {
        if ($this->type == self::HREFSTATUS) {
            throw new \Exception();
        }

        if (!isset($this->properties[$status])) {
            $this->properties[$status] = new PropertySet();
        }

        $this->properties[$status]->add($property);

        return $this;
    }
}
