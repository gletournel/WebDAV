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
 * @covers Grale\WebDav\Property\LockDiscovery
 */
class LockDiscoveryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var LockDiscovery
     */
    protected $property;

    public function setUp()
    {
        $lockOne = new Lock('shared');
        $lockOne->setToken('opaquelocktoken:e71df4fae-5dec-22d6-fea5-00a0c91e6be4');

        $lockTwo = new Lock('exclusive');
        $lockTwo->setToken('opaquelocktoken:e71d4fae-5dec-22df-fea5-00a0c93bd5eb1');

        $this->property = new LockDiscovery(array($lockOne, $lockTwo));
    }

    public function testGetValue()
    {
        $this->assertCount(2, $this->property->getValue());
    }

    public function testGetLocks()
    {
        $this->assertCount(2, $this->property->getLocks());
    }

    public function testGetWriteLocks()
    {
        $this->assertCount(2, $this->property->getLocks('write'));
    }

    public function testGetSharedLocks()
    {
        $this->assertCount(1, $this->property->getLocks('write', 'shared'));
    }

    public function testGetExclusiveLocks()
    {
        $this->assertCount(1, $this->property->getLocks('write', 'exclusive'));
    }

    public function testHasLock()
    {
        $this->assertTrue($this->property->hasLock());
    }

    public function testHasWriteLock()
    {
        $this->assertTrue($this->property->hasLock('write'));
    }

    public function testHasExclusiveLock()
    {
        $this->assertTrue($this->property->hasLock('write', 'exclusive'));
    }

    public function testHasSharedLock()
    {
        $this->assertTrue($this->property->hasLock('write', 'shared'));
    }

    public function testHasLockToken()
    {
        $this->assertTrue($this->property->hasLockToken('opaquelocktoken:e71df4fae-5dec-22d6-fea5-00a0c91e6be4'));
    }

    public function testGetLock()
    {
        $result = $this->property->getLock('opaquelocktoken:e71df4fae-5dec-22d6-fea5-00a0c91e6be4');
        $this->assertInstanceOf('\Grale\WebDav\Lock', $result);
    }

    public function testFromXmlWitoutAnyActiveLock()
    {
        $dom = new \DOMDocument();
        $dom->loadXML(
            '<?xml version="1.0" encoding="utf-8"?>
            <D:multistatus xmlns:D="DAV:">
              <D:lockdiscovery/>
            </D:multistatus>'
        );
        $property = LockDiscovery::fromXml($dom->documentElement);

        $this->assertCount(0, $property->getLocks());
    }

    public function testFromXml()
    {
        $dom = new \DOMDocument();
        $dom->loadXML(
            '<?xml version="1.0" encoding="utf-8"?>
            <D:multistatus xmlns:D="DAV:">
              <D:lockdiscovery>
                <D:activelock>
                  <D:locktype><D:write/></D:locktype>
                  <D:lockscope><D:exclusive/></D:lockscope>
                  <D:depth>Infinity</D:depth>
                  <D:owner>
                    <D:href>http://example.org/~ejw/contact.html</D:href>
                  </D:owner>
                  <D:timeout>Second-604800</D:timeout>
                  <D:locktoken>
                    <D:href>urn:uuid:e71d4fae-5dec-22d6-fea5-00a0c91e6be4</D:href>
                  </D:locktoken>
                  <D:lockroot>
                    <D:href>http://example.com/workspace/webdav/proposal.doc</D:href>
                  </D:lockroot>
                </D:activelock>
              </D:lockdiscovery>
            </D:multistatus>'
        );
        $property = LockDiscovery::fromXml($dom->documentElement);

        $this->assertCount(1, $property->getLocks());
        $this->assertTrue($property->hasLockToken('urn:uuid:e71d4fae-5dec-22d6-fea5-00a0c91e6be4'));
    }
}
