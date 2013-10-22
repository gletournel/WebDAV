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

use Grale\WebDav\Header\TimeoutHeader;
use Grale\WebDav\Header\DepthHeader;

/**
 * @covers Grale\WebDav\Lock
 */
class LockTest extends \PHPUnit_Framework_TestCase
{
    protected $lock;

    public function setUp()
    {
        $this->lock = new Lock();
    }

    public function testSharedLock()
    {
        $lock = new Lock('shared');
        $this->assertEquals('shared', $lock->getScope());
        $this->assertTrue($lock->isShared());
    }

    public function testType()
    {
        $this->assertEquals('write', $this->lock->getType());
    }

    public function testDefaultScope()
    {
        $this->assertEquals('exclusive', $this->lock->getScope());
        $this->assertTrue($this->lock->isExclusive());
    }

    public function testDefaultDepth()
    {
        $this->assertEquals(0, $this->lock->getDepth());
    }

    public function testInfiniteDepth()
    {
        $this->lock->setDepth(DepthHeader::INFINITY);
        $this->assertEquals(DepthHeader::INFINITY, $this->lock->getDepth());
        $this->assertTrue($this->lock->isDeep());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Values other than 0 or infinity MUST NOT be used with the Depth header on a lock
     */
    public function testInvalidDepth()
    {
        $this->lock->setDepth(1);
    }

    public function testOwner()
    {
        $this->lock->setOwner('admin@host.com');
        $this->assertEquals('admin@host.com', $this->lock->getOwner());
    }

    public function testToken()
    {
        $this->lock->setToken('opaquelocktoken:f81d4fae-7dec-11d0-a765-00a0c91e6bf6');
        $this->assertEquals('opaquelocktoken:f81d4fae-7dec-11d0-a765-00a0c91e6bf6', $this->lock->getToken());
    }

    public function testTimeout()
    {
        $this->lock->setTimeout(3600);
        $this->assertEquals(3600, $this->lock->getTimeout());
    }

    public function testSetTimeoutAsObject()
    {
        $this->lock->setTimeout(TimeoutHeader::getInfinite());
        $this->assertEquals(TimeoutHeader::INFINITE, $this->lock->getTimeout());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The lock type specified is not supported
     */
    public function testInvalidLockType()
    {
        new Lock('exclusive', 'read');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The locking mechanism specified is not supported
     */
    public function testInvalidLockScope()
    {
        new Lock('advisory');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage
     */
    public function testFromInvalidXml()
    {
        $dom = new \DOMDocument();
        $dom->loadXML('<?xml version="1.0" encoding="utf-8"?><element/>');
        Lock::fromXml($dom->documentElement);
    }

    public function testFromActiveLockXml()
    {
        $dom = new \DOMDocument();
        $dom->loadXML(
            '<?xml version="1.0" encoding="utf-8"?>
            <D:activelock xmlns:D="DAV:">
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
            </D:activelock>'
        );
        $lock = Lock::fromXml($dom->documentElement);

        $this->assertEquals('write', $lock->getType());
        $this->assertEquals('exclusive', $lock->getScope());
        $this->assertEquals(DepthHeader::INFINITY, $lock->getDepth());
        $this->assertEquals('http://example.org/~ejw/contact.html', $lock->getOwner());
        $this->assertEquals(604800, $lock->getTimeout());
        $this->assertEquals('urn:uuid:e71d4fae-5dec-22d6-fea5-00a0c91e6be4', $lock->getToken());
    }

    public function testFromLockInfoXml()
    {
        $dom = new \DOMDocument();
        $dom->loadXML(
            '<?xml version="1.0" encoding="utf-8"?>
            <D:lockinfo xmlns:D="DAV:">
              <D:locktype><D:write/></D:locktype>
              <D:lockscope><D:exclusive/></D:lockscope>
              <D:owner>
                <D:href>http://example.org/~ejw/contact.html</D:href>
              </D:owner>
            </D:lockinfo>'
        );
        $lock = Lock::fromXml($dom->documentElement);
        $this->assertEquals('write', $lock->getType());
        $this->assertEquals('exclusive', $lock->getScope());
        $this->assertEquals('http://example.org/~ejw/contact.html', $lock->getOwner());
        $this->assertEmpty($lock->getToken());
    }
}
