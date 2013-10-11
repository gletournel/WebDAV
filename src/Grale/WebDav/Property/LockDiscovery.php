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

use Grale\WebDav\Lock;

/**
 * Describes the active locks on a resource
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
final class LockDiscovery extends AbstractProperty
{
    /**
     * @var array
     */
    protected $locks;

    /**
     * @param array $locks
     */
    public function __construct(array $locks = array())
    {
        $this->setName('D:lockdiscovery');
        $this->locks = $locks;
    }

    /**
     * @return array
     */
    public function getValue()
    {
        return $this->locks;
    }

    /**
     * @return array
     */
    public function getLocks()
    {
        return $this->locks;
    }

    /**
     * @param string $type
     * @param string $scope
     *
     * @return bool
     */
    public function hasLock($type = null, $scope = null)
    {
    }

    /**
     * @param string $type
     * @param string $scope
     *
     * @return Lock
     */
    public function getLock($type = null, $scope = null)
    {
    }

    /**
     * @inheritdoc
     */
    public static function fromXml(\DOMElement $element, array $xmlNamespaces = array())
    {
        $locks = array();

        foreach ($element->getElementsByTagNameNS('DAV:', 'activelock') as $xActiveLock) {
            $locks[] = Lock::fromXml($xActiveLock);
        }

        return new self($locks);
    }
}
