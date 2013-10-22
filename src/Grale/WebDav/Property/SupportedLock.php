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

/**
 * Provides a listing of the lock capabilities supported by a resource
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
class SupportedLock extends AbstractProperty
{
    /**
     * Element name as described in the WebDAV XML elements definition
     */
    const TAGNAME = 'D:supportedlock';

    /**
     * @var array The lock capabilities
     */
    protected $capabilities;

    /**
     * @param array $capabilities The lock capabilities
     */
    public function __construct(array $capabilities = array())
    {
        $this->setName(self::TAGNAME);
        $this->capabilities = $capabilities;
    }

    /**
     * @return array Returns the lock capabilities as an array
     */
    public function getValue()
    {
        return $this->capabilities;
    }

    /**
     *
     * Note that this method does not define whether a lock can be successfully executed.
     *
     * @param string $type  The lock type
     * @param string $scope The scope of the lock
     *
     * @return bool Returns true if associated resource supports any locking or false otherwise
     */
    public function isLockable($type = null, $scope = null)
    {
        $result = false;

        if ($type === null) {
            $result = count($this->capabilities) > 0;
        } elseif ($scope === null) {
            $result = isset($this->capabilities[$type]);
        } else {
            $result = isset($this->capabilities[$type]) && in_array($scope, $this->capabilities[$type]);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public static function fromXml(\DOMElement $element, array $xmlNamespaces = array())
    {
        $value = array();

        foreach ($element->getElementsByTagNameNS('DAV:', 'lockentry') as $xEntry) {
            $locktype  = null;
            $lockscope = null;

            // search for WebDAV XML elements
            $types  = $xEntry->getElementsByTagNameNS('DAV:', 'locktype');
            $scopes = $xEntry->getElementsByTagNameNS('DAV:', 'lockscope');

            if ($types->length > 0 && $scopes->length > 0) {
                $locktype   = $types->item(0)->hasChildNodes() ? $types->item(0)->firstChild->localName : null;
                $lockscope  = $scopes->item(0)->hasChildNodes() ? $scopes->item(0)->firstChild->localName : null;
            }

            if ($locktype && $lockscope) {
                if (!isset($value[$locktype])) {
                    $value[$locktype] = array();
                }

                $value[$locktype][] = $lockscope;
            }
        }

        return new self($value);
    }
}
