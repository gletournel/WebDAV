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

use Grale\WebDav\Property\DateTimeProperty;
use Grale\WebDav\Property\SupportedLock;
use Grale\WebDav\Property\LockDiscovery;
use Grale\WebDav\Property\ResourceType;

/**
 * Represents a resource on the WebDAV server
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
class Resource implements LockableInterface
{
    /**
     * @var string
     */
    protected $href;

    /**
     * @var PropertySet
     */
    protected $properties;

    /**
     * @param string $href
     * @param PropertySet|array
     * @throws \InvalidArgumentException
     */
    public function __construct($href, $properties = null)
    {
        $this->href = $href;

        if (is_array($properties)) {
            $this->properties = new PropertySet($properties);
        } elseif ($properties instanceof PropertySet) {
            $this->properties = $properties;
        } elseif ($properties === null) {
            $this->properties = new PropertySet();
        } else {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * @return string Returns the absolute href of this resource as returned in the multi-status response body
     */
    public function getHref()
    {
        return $this->href;
    }

    /**
     * @return array
     */
    public function getType()
    {
        return $this->hasProperty(ResourceType::TAGNAME)
             ? $this->getProperty(ResourceType::TAGNAME)->getValue()
             : array();
    }

    /**
     * @return string
     */
    public function getEtag()
    {
        return $this->hasProperty('D:getetag')
             ? $this->getProperty('D:getetag')->getValue()
             : null;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return basename($this->href);
    }

    /**
     * Returns the display name of this resource.
     *
     * @return string The display name of this resource
     */
    public function getDisplayName()
    {
        return $this->hasProperty('D:displayname')
             ? $this->getProperty('D:displayname')->getValue()
             : null;
    }

    /**
     * @return \DateTime
     */
    public function getCreationDate()
    {
        $result = null;

        if (($prop = $this->getProperty('D:creationdate')) && $prop instanceof DateTimeProperty) {
            $result = $prop->getTime();
        }

        return $result;
    }

    /**
     * @return \DateTime
     */
    public function getLastModified()
    {
        $result = null;

        if (($prop = $this->getProperty('D:getlastmodified')) && $prop instanceof DateTimeProperty) {
            $result = $prop->getTime();
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getContentLanguage()
    {
        return $this->hasProperty('D:getcontentlanguage')
             ? $this->getProperty('D:getcontentlanguage')->getValue()
             : null;
    }

    /**
     * @return int
     */
    public function getContentLength()
    {
        $bytes = 0;

        if ($this->hasProperty('D:getcontentlength')) {
            $bytes = (int)$this->getProperty('D:getcontentlength')->getValue();
        }

        return $bytes;
    }

    /**
     * Returns the mime-type of this resource.
     *
     * @return string
     */
    public function getContentType()
    {
        return $this->hasProperty('D:getcontenttype')
             ? $this->getProperty('D:getcontenttype')->getValue()
             : null;
    }

    /**
     * @inheritdoc
     */
    public function hasLock($type = null, $scope = null)
    {
        $result = false;

        if (($prop = $this->getProperty(LockDiscovery::TAGNAME)) && $prop instanceof LockDiscovery) {
            $result = $prop->hasLock($type, $scope);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getLocks($type = null, $scope = null)
    {
        $result = array();

        if (($prop = $this->getProperty(LockDiscovery::TAGNAME)) && $prop instanceof LockDiscovery) {
            $result = $prop->getLocks($type, $scope);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function hasLockToken($lockToken)
    {
        $result = false;

        if (($prop = $this->getProperty(LockDiscovery::TAGNAME)) && $prop instanceof LockDiscovery) {
            $result = $prop->hasLockToken($lockToken);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getLock($lockToken)
    {
        $result = null;

        if (($prop = $this->getProperty(LockDiscovery::TAGNAME)) && $prop instanceof LockDiscovery) {
            $result = $prop->getLock($lockToken);
        }

        return $result;
    }

    /**
     *
     * Note that this method does not define whether a lock can be successfully executed.
     *
     * @param string $type  The lock type
     * @param string $scope The scope of the lock
     *
     * @return bool Returns true if the resource supports any locking or false otherwise
     */
    public function isLockable($type = null, $scope = null)
    {
        $result = false;

        if (($prop = $this->getProperty(SupportedLock::TAGNAME)) && $prop instanceof SupportedLock) {
            $result = $prop->isLockable($type, $scope);
        }

        return $result;
    }

    /**
     * @return bool Returns true if the resource represents a collection resource
     */
    public function isCollection()
    {
        $result = false;

        if (($prop = $this->getProperty(ResourceType::TAGNAME)) && $prop instanceof ResourceType) {
            $result = $prop->isCollection();
        }

        return $result;
    }

    /**
     * Returns an array of all property names available on this resource.
     *
     * @return array Returns an array of property names
     */
    public function getPropertyNames()
    {
        return $this->properties->getNames();
    }

    /**
     * Returns all properties present on this resource.
     *
     * @return PropertySet
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasProperty($name)
    {
        return $this->properties->has($name);
    }

    /**
     * Returns the property with the specified name.
     *
     * @param string $name The name of the property
     *
     * @return PropertyInterface Returns the property with the given name or <tt>null</tt>
     * if the property does not exist
     */
    public function getProperty($name)
    {
        return $this->properties->get($name);
    }

    /**
     * @param PropertyInterface $property
     */
    public function setProperty(PropertyInterface $property)
    {
        $this->properties->add($property);
    }

    /**
     * @return array
     */
    public function getStat()
    {
        static $statTemplate = array(
            0  =>  0, 'dev'     =>  0, // Device number
            1  =>  0, 'ino'     =>  0, // File's inode number
            2  =>  0, 'mode'    =>  0, // File type and permissions
            3  =>  0, 'nlink'   =>  0, // Number of hard links to file
            4  =>  0, 'uid'     =>  0, // User ID of file's owner
            5  =>  0, 'gid'     =>  0, // Group ID of file's owner
            6  => -1, 'rdev'    => -1, // Device type, if inode device
            7  =>  0, 'size'    =>  0, // The size of file in bytes
            8  =>  0, 'atime'   =>  0, // The time (UNIX timestamp) file was last accessed
            9  =>  0, 'mtime'   =>  0, // The time (UNIX timestamp) file was last modified
            10 =>  0, 'ctime'   =>  0, // The time (UNIX timestamp) of when the inode was last changed
            11 => -1, 'blksize' => -1, // Optimal file system I/O operation block size
            12 => -1, 'blocks'  => -1, // Number of 512-byte blocks allocated
        );

        $stat = $statTemplate;

        if ($date = $this->getCreationDate()) {
            $stat['ctime'] = $stat[10] = $date->getTimestamp();
        }

        if ($date = $this->getLastModified()) {
            $stat['atime'] = $stat[8] = $stat['mtime'] = $stat[9] = $date->getTimestamp();
        }

        if ($this->isCollection()) {
            // Directory with 0777 access - see "man 2 stat"
            $stat['mode'] = $stat[2] = 0040777;
        } else {
            // Regular file with 0777 access - see "man 2 stat"
            $stat['mode'] = $stat[2] = 0100777;
        }

        $stat['size'] = $stat[7] = $this->getContentLength();

        return $stat;
    }
}
