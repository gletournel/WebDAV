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

use Grale\WebDav\Header\DepthHeader;
use Grale\WebDav\Header\TimeoutHeader;

/**
 *
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
class Lock
{
    /**
     * A shared lock
     */
    const SHARED = 'shared';

    /**
     * An exclusive lock
     */
    const EXCLUSIVE = 'exclusive';

    /**
     * A write lock
     */
    const WRITE = 'write';

    /**
     * @var string The lock type
     */
    protected $type;

    /**
     * @var string Exclusive or shared lock
     */
    protected $scope;

    /**
     * @var int Depth of lock
     */
    protected $depth;

    /**
     * @var string The owner of the lock
     */
    protected $owner;

    /**
     * @var string The locktoken
     */
    protected $token;

    /**
     * @var TimeoutHeader Number of seconds until the lock is expiring
     */
    protected $timeout;

    /**
     * @param string $scope Exclusive or shared lock
     * @param string $type  The lock type
     */
    public function __construct($scope = null, $type = 'write')
    {
        if ($scope === null) {
            $scope = self::EXCLUSIVE;
        }

        $this->type  = $type;
        $this->scope = $scope;
    }

    /**
     * @return bool
     */
    public function isExclusive()
    {
        return $this->scope == self::EXCLUSIVE;
    }

    /**
     * @return bool
     */
    public function isShared()
    {
        return $this->scope == self::SHARED;
    }

    /**
     * @return string
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $depth
     */
    public function setDepth($depth)
    {
        if ($depth != DepthHeader::INFINITY && $depth != 0) {
            throw new \InvalidArgumentException(
                'Values other than 0 or infinity MUST NOT be used with the Depth header on a lock'
            );
        }

        $this->depth = $depth;
    }

    /**
     * @return int
     */
    public function getDepth()
    {
        return $this->depth;
    }

    /**
     * @param string $owner
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
    }

    /**
     * @return string
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param string $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param mixed $timeout
     */
    public function setTimeout($timeout)
    {
        if ($timeout instanceof TimeoutHeader) {
            $this->timeout = $timeout;
        } elseif (is_int($timeout)) {
            $this->timeout = new TimeoutHeader($timeout);
        } else {
            $this->timeout = TimeoutHeader::parse($timeout);
        }
    }

    /**
     * @return TimeoutHeader
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->token;
    }

    /**
     * @return self
     */
    public static function fromXml(\DOMElement $element)
    {
        $xpath = new \DOMXPath($element->ownerDocument);
        $xpath->registerNamespace('D', 'DAV:');

        $xLockType  = $xpath->query('D:locktype/*', $element);
        $xLockScope = $xpath->query('D:lockscope/*', $element);

        if ($xLockType->length == 0 || $xLockScope->length == 0) {
            throw new \RuntimeException();
        }

        $result = new self(
            $xLockScope->item(0)->localName,
            $xLockType->item(0)->localName
        );

        if ($depth = $xpath->evaluate('string(D:depth)', $element)) {
            $result->setDepth(DepthHeader::parse($depth));
        }

        if ($timeout = $xpath->evaluate('string(D:timeout)', $element)) {
            $result->setTimeout($timeout);
        }

        $xOwner = $xpath->query('D:owner', $element);

        if ($xOwner->length) {
            $result->setOwner(trim($xOwner->item(0)->textContent));
        }

        $xLockToken = $xpath->query('D:locktoken', $element);

        if ($xLockToken->length) {
            $result->setToken(trim($xLockToken->item(0)->textContent));
        }

        return $result;
    }

    /**
     * @param Client $client
     * @param string $xml
     *
     * @return self
     *
     * @todo
     * - validate the XML document using a WebDAV DTD
     * - register namespaces automatically with the Xpath object
     * - testing DOMDocument::loadXML throwing DOMException with the libxml settings and an erroneous XML document
     */
    public static function parse(Client $client, $xml)
    {
        $xml = preg_replace('/\s*[\r\n]\s*/', null, $xml);

        $dom = new \DOMDocument();

        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;

        $dom->loadXML($xml, LIBXML_NOWARNING|LIBXML_NOERROR);

        // XSD validation ? Namespaces ?

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('D', 'DAV:');

        if (!$dom->hasChildNodes()) {
            throw new \RuntimeException();
        }

        $lockInstance = null;
        $xActiveLocks = $xpath->query('./D:lockdiscovery/D:activelock', $dom->documentElement);

        if ($xActiveLocks->length > 0) {
            $lockInstance = self::fromXml($xActiveLocks->item(0));
        }

        return $lockInstance;
    }
}
