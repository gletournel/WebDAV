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
class Resource
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
     * @return string
     */
    public function getPath()
    {
    }

    /**
     * @return string
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
        return $this->hasProperty('D:resourcetype') ? $this->getProperty('D:resourcetype')->getValue() : null;
    }

    /**
     * @return string
     */
    public function getEtag()
    {
        return $this->hasProperty('D:getetag') ? $this->getProperty('D:getetag')->getValue() : null;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return basename($this->href);
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return $this->hasProperty('D:displayname') ? $this->getProperty('D:displayname')->getValue() : null;
    }

    /**
     * @return \DateTime
     */
    public function getCreationDate()
    {
        return $this->hasProperty('D:creationdate') ? $this->getProperty('D:creationdate')->getTime() : null;
    }

    /**
     * @return \DateTime
     */
    public function getLastModified()
    {
        return $this->hasProperty('D:getlastmodified') ? $this->getProperty('D:getlastmodified')->getTime() : null;
    }

    /**
     * @return string
     */
    public function getContentLanguage()
    {
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
     * @return string
     */
    public function getContentType()
    {
    }

    /**
     * @return array
     */
    public function getLocks()
    {
    }

    /**
     * @param string $scope
     * @param string $type
     *
     * @return Lock
     */
    public function getLock($scope = null, $type = null)
    {
    }

    /**
     * @param string $scope
     * @param string $type
     *
     * @return bool
     */
    public function hasLock($scope = null, $type = null)
    {
    }

    /**
     * @param string $scope
     * @param string $type
     *
     * @return bool
     */
    public function isLockable($scope = null, $type = null)
    {
    }

    /**
     * @return bool
     */
    public function isCollection()
    {
        return !$this->properties->has('D:resourcetype') ?: $this->properties->get('D:resourcetype')->isCollection();
    }

    /**
     * @return array
     */
    public function getPropertyNames()
    {
        return $this->properties->getNames();
    }

    /**
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
     * @param string $name
     * @return PropertyInterface
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
            $stat['mode'] &= ~0100000; // clear S_IFREG
            $stat['mode'] |= 040000;   // set S_IFDIR
            $stat[2] = $stat['mode'];

            // Directory with 0777 access - see "man 2 stat"
            $stat['mode'] = $stat[2] = 0040777;
        } else {
            // Regular file with 0777 access - see "man 2 stat"
            $stat['mode'] = $stat[2] = 0100777;
        }

        $stat['size'] = $stat[7] = $this->getContentLength();

        return $stat;
    }

    /**
     * @return string
     */
    public function __toString()
    {
    }
}
