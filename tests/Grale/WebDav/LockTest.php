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

class LockTest extends \PHPUnit_Framework_TestCase
{
    protected $lock;

    public function setUp()
    {
        $this->lock = new Lock();
    }

    public function testType()
    {
        $this->assertEquals('write', $this->lock->getType());
    }

    public function testDefaultScope()
    {
        $this->assertEquals(Lock::EXCLUSIVE, $this->lock->getScope());
    }

    public function testSharedLock()
    {
        $lock = new Lock(Lock::SHARED);
        $this->assertEquals(Lock::SHARED, $lock->getScope());
    }

    public function testIsSharedLock()
    {
        $lock = new Lock(Lock::SHARED);
        $this->assertTrue($lock->isShared());
    }

    public function testExclusiveLock()
    {
        $lock = new Lock(Lock::EXCLUSIVE);
        $this->assertEquals(Lock::EXCLUSIVE, $lock->getScope());
    }

    public function testIsExclusiveLock()
    {
        $lock = new Lock(Lock::EXCLUSIVE);
        $this->assertTrue($lock->isExclusive());
    }

    public function testDefaultDepth()
    {
        $this->assertEquals(0, $this->lock->getDepth());
    }

    public function testInfiniteDepth()
    {
        $this->lock->setDepth(DepthHeader::INFINITY);
        $this->assertEquals(DepthHeader::INFINITY, $this->lock->getDepth());
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
        $this->assertEquals(3600, $this->lock->getTimeout()->getSeconds());
    }

    public function testFromXml()
    {
        $str = '<?xml version="1.0" encoding="utf-8" ?>
    <D:activelock xmlns:D="DAV:">
      <D:locktype><D:write/></D:locktype>
      <D:lockscope><D:shared/></D:lockscope>
      <D:depth>Infinity</D:depth>
      <D:owner>
        <D:href>http://www.ics.uci.edu/~ejw/contact.html</D:href>
      </D:owner>
      <D:timeout>Second-604800</D:timeout>
      <D:locktoken>
        <D:href>opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4</D:href>
      </D:locktoken>
    </D:activelock>';

        $dom = new \DOMDocument();
        $dom->loadXML($str);

        $lock = Lock::fromXml($dom->documentElement);
    }
}
