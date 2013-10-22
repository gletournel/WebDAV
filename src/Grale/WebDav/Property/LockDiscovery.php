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
use Grale\WebDav\LockableInterface;

/**
 * Describes the active locks on a resource
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
class LockDiscovery extends AbstractProperty implements LockableInterface
{
    /**
     * Element name as described in the WebDAV XML elements definition
     */
    const TAGNAME = 'D:lockdiscovery';

    /**
     * @var array The list of active locks
     */
    protected $locks = array();

    /**
     * @var array An index of active locks, for speeding up searches
     */
    protected $index = array();

    /**
     * @var array An index of active lock tokens
     */
    protected $tokens = array();

    /**
     * @param array $locks A list of active locks
     */
    public function __construct(array $locks = array())
    {
        $this->setName(self::TAGNAME);

        foreach ($locks as $activeLock) {
            $this->add($activeLock);
        }
    }

    /**
     * Add an lock to the list of active locks
     *
     * @param Lock $lock The lock to add
     */
    private function add(Lock $lock)
    {
        $index = count($this->locks);

        if ($lock->getToken()) {
            $this->tokens[$lock->getToken()] = $index;
        }

        $type    = $lock->getType();
        $scope   = $lock->getScope();
        $hashKey = $this->getHashKey($type, $scope);

        if (!isset($this->index[$type])) {
            $this->index[$type] = array();
        }
        if (!isset($this->index[$hashKey])) {
            $this->index[$hashKey] = array();
        }

        $this->index[$type][] = $index;
        $this->index[$hashKey][] = $index;

        $this->locks[] = $lock;
    }

    /**
     * Computes a hash key for the given lock type and lock scope
     *
     * @param string $type  The lock type
     * @param string $scope The lock scope
     *
     * @return string The hash key for the index table
     */
    private function getHashKey($type, $scope)
    {
        $hashKey = null;

        if ($type && $scope) {
            $hashKey = "{$type}{$scope}";
        }

        return $hashKey;
    }

    /**
     * @return array Returns the list of active locks
     */
    public function getValue()
    {
        return $this->locks;
    }

    /**
     * @inheritdoc
     */
    public function hasLock($type = null, $scope = null)
    {
        $hashKey = $this->getHashKey($type, $scope);

        return $hashKey ? isset($this->index[$hashKey]) : count($this->locks) > 0;
    }

    /**
     * @inheritdoc
     */
    public function getLocks($type = null, $scope = null)
    {
        $result  = array();
        $hashKey = $this->getHashKey($type, $scope);

        if ($hashKey && isset($this->index[$hashKey])) {
            foreach ($this->index[$hashKey] as $index) {
                $result[] = $this->locks[$index];
            }
        } elseif ($hashKey === null) {
            $result = $this->locks;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function hasLockToken($lockToken)
    {
        return isset($this->tokens[$lockToken]);
    }

    /**
     * @inheritdoc
     */
    public function getLock($lockToken)
    {
        return isset($this->tokens[$lockToken]) ? $this->locks[$this->tokens[$lockToken]] : null;
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
